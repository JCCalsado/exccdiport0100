<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Transaction;
use App\Models\Fee;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\StudentPaymentTerm;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Role is a STRING, not an enum
        $adminRoles = ['super_admin', 'admin', 'accounting'];

        if (in_array($user->role, $adminRoles)) {
            // Admin/accounting: all transactions
            $transactions = Transaction::with('user')
                ->orderByDesc('year')
                ->orderBy('semester')
                ->get()
                ->groupBy(fn($txn) => "{$txn->year} {$txn->semester}");
        } else {
            // Students: only their own
            $transactions = $user->transactions()
                ->with('user')
                ->orderByDesc('year')
                ->orderBy('semester')
                ->get()
                ->groupBy(fn($txn) => "{$txn->year} {$txn->semester}");
        }

        return Inertia::render('Transactions/Index', [
            'auth'               => ['user' => $user],
            'transactionsByTerm' => $transactions,
            'account'            => $user->account,
            'currentTerm'        => $this->getCurrentTerm(),
        ]);
    }

    private function getCurrentTerm(): string
    {
        $year  = now()->year;
        $month = now()->month;

        if ($month >= 6 && $month <= 10) {
            $semester = '1st Sem';
        } elseif ($month >= 11 || $month <= 3) {
            $semester = '2nd Sem';
        } else {
            $semester = 'Summer';
        }

        return "{$year} {$semester}";
    }

    public function create()
    {
        return Inertia::render('Transactions/Create', [
            'users' => User::select('id', 'name', 'email')->get(),
        ]);
    }

    public function store(Request $request)
    {
        // FIX: role is string → remove ->value
        if (!in_array($request->user()->role, ['super_admin', 'admin', 'accounting'])) {
            abort(403, 'Unauthorized action.');
        }

        $data = $request->validate([
            'user_id'         => 'required|exists:users,id',
            'amount'          => 'required|numeric|min:0.01',
            'type'            => 'required|in:charge,payment',
            'payment_channel' => 'nullable|string',
        ]);

        $transaction = Transaction::create([
            'user_id'         => $data['user_id'],
            'reference'       => 'SYS-' . Str::upper(Str::random(8)),
            'amount'          => $data['amount'],
            'type'            => $data['type'],
            'kind'            => $data['type'], // normalize
            'status'          => $data['type'] === 'payment' ? 'paid' : 'pending',
            'payment_channel' => $data['payment_channel'],
        ]);

        $this->recalculateAccount($transaction->user);

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transaction created successfully!');
    }

    public function show(Transaction $transaction)
    {
        return Inertia::render('Transactions/Show', [
            'transaction' => $transaction->load('user'),
        ]);
    }

    public function payNow(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'reference_number' => 'nullable|string',
            'paid_at' => 'required|date',
            'description' => 'required|string',
            'term_id' => 'nullable|exists:student_payment_terms,id', // Optional: specify which term
        ]);

        DB::beginTransaction();
        try {
            // Create payment transaction
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'reference' => 'PAY-' . Str::upper(Str::random(8)),
                'kind' => 'payment',
                'type' => 'Payment',
                'amount' => $data['amount'],
                'status' => 'paid',
                'payment_channel' => $data['payment_method'],
                'paid_at' => $data['paid_at'],
                'meta' => [
                    'reference_number' => $data['reference_number'] ?: null,
                    'description' => $data['description'],
                    'term_id' => $data['term_id'] ?? null,
                ],
            ]);

            // Update payment term(s)
            if (isset($data['term_id'])) {
                // Apply to specific term
                $term = StudentPaymentTerm::findOrFail($data['term_id']);
                $term->paid_amount += $data['amount'];
                if ($term->paid_amount >= $term->amount) {
                    $term->status = 'paid';
                } elseif ($term->paid_amount > 0) {
                    $term->status = 'partial';
                }
                $term->save();
            } else {
                // Apply to earliest unpaid term(s)
                $remainingAmount = $data['amount'];
                $terms = StudentPaymentTerm::where('user_id', $user->id)
                    ->unpaid()
                    ->orderBy('term_order')
                    ->get();

                foreach ($terms as $term) {
                    if ($remainingAmount <= 0) break;

                    $termBalance = $term->amount - $term->paid_amount;
                    $paymentForThisTerm = min($remainingAmount, $termBalance);

                    $term->paid_amount += $paymentForThisTerm;
                    if ($term->paid_amount >= $term->amount) {
                        $term->status = 'paid';
                    } else {
                        $term->status = 'partial';
                    }
                    $term->save();

                    $remainingAmount -= $paymentForThisTerm;
                }
            }

            DB::commit();

            return redirect()
                ->route('student.account')
                ->with('success', 'Payment recorded successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Payment recording failed', [
                'error' => $e->getMessage(),
            ]);
            
            return back()->withErrors([
                'error' => 'Failed to record payment. Please try again.'
            ]);
        }
    }

    protected function recalculateAccount($user): void
    {
        $charges  = $user->transactions()->where('kind', 'charge')->sum('amount');
        $payments = $user->transactions()->where('kind', 'payment')->where('status', 'paid')->sum('amount');
        $balance  = $charges - $payments;

        $account = $user->account ?? $user->account()->create();
        $account->update(['balance' => $balance]);
    }

    protected function checkAndPromoteStudent($student)
    {
        if (!$student || !$student->user) {
            return;
        }

        $account = $student->user->account;

        if ($account && $account->balance <= 0) {
            $this->promoteYearLevel($student);
            $this->assignNextPayables($student);
        }
    }

    protected function promoteYearLevel($student)
    {
        $levels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];

        $index = array_search($student->year_level, $levels);

        if ($index !== false && $index < count($levels) - 1) {
            $student->year_level = $levels[$index + 1];
            $student->save();
        }
    }

    protected function assignNextPayables($student)
    {
        $fees = Fee::where('year_level', $student->year_level)
            ->where('semester', '1st Sem')
            ->get();

        foreach ($fees as $fee) {
            $student->user->transactions()->create([
                'reference' => 'FEE-' . strtoupper(Str::slug($fee->name)) . '-' . $student->id,
                'kind'      => 'charge',
                'type'      => $fee->name,
                'amount'    => $fee->amount,
                'status'    => 'pending',
                'meta'      => ['description' => $fee->name],
            ]);
        }
    }

    public function download()
    {
        $transactions = Transaction::with('fee')
            ->orderBy('created_at', 'desc')
            ->get();

        $pdf = Pdf::loadView('pdf.transactions', [
            'transactions' => $transactions
        ]);

        return $pdf->download('transactions.pdf');
    }
}

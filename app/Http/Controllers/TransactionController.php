<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Transaction;
use App\Models\Fee;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use App\Services\AccountService;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Admins & accounting see all, students see their own
        if (in_array($user->role->value, ['super_admin', 'admin', 'accounting'])) {
            $transactions = Transaction::with('user')
                ->orderByDesc('year')
                ->orderBy('semester')
                ->get()
                ->groupBy(fn($txn) => "{$txn->year} {$txn->semester}");
        } else {
            $transactions = $user->transactions()
                ->with('user')
                ->orderByDesc('year')
                ->orderBy('semester')
                ->get()
                ->groupBy(fn($txn) => "{$txn->year} {$txn->semester}");
        }

        return Inertia::render('Transactions/Index', [
            'auth' => ['user' => $user],
            'transactionsByTerm' => $transactions,
            'account' => $user->account,
            'currentTerm' => $this->getCurrentTerm(),
        ]);
    }

    private function getCurrentTerm(): string
    {
        $year = now()->year;
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
        $users = User::select('id', 'name', 'email')->get();

        return Inertia::render('Transactions/Create', [
            'users' => $users,
        ]);
    }

    public function store(Request $request)
    {
        // Only staff can create transactions
        if (!in_array($request->user()->role->value, ['super_admin', 'admin', 'accounting'])) {
            abort(403, 'Unauthorized action.');
        }

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:charge,payment',
            'payment_channel' => 'nullable|string',
        ]);

        $transaction = Transaction::create([
            'user_id' => $data['user_id'],
            'reference' => 'SYS-' . Str::upper(Str::random(8)),
            'amount' => $data['amount'],
            'type' => $data['type'],
            'status' => $data['type'] === 'payment' ? 'paid' : 'pending',
            'payment_channel' => $data['payment_channel'] ?? null,
        ]);

        // Recalculate balance
        $this->recalculateAccount($transaction->user);

        return redirect()->route('transactions.index')
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
        ]);

        $tx = Transaction::create([
            'user_id' => $user->id,
            'reference' => 'PAY-' . Str::upper(Str::random(8)),
            'kind' => 'payment',
            'type' => 'Payment',
            'amount' => $data['amount'],
            'status' => 'paid',
            'payment_channel' => $data['payment_method'],
            'paid_at' => $data['paid_at'],
            'meta' => [
                'reference_number' => $data['reference_number'] ?? null,
                'description' => $data['description'],
            ],
        ]);

        // Update account balance
        $this->recalculateAccount($user);

        // ✅ Only check promotion if user has a student profile
        if ($user->role->value === 'student' && $user->student) {
            $this->checkAndPromoteStudent($user->student);
        }

        return redirect()->route('student.account')
            ->with('success', 'Payment recorded successfully.');
    }

    protected function recalculateAccount($user): void
    {
        $charges = $user->transactions()->where('kind', 'charge')->sum('amount');
        $payments = $user->transactions()->where('kind', 'payment')->where('status', 'paid')->sum('amount');
        $balance = $charges - $payments;

        $account = $user->account ?? $user->account()->create();
        $account->update(['balance' => $balance]);
    }

    protected function checkAndPromoteStudent($student)
    {
        if (!$student) {
            return;
        }

        $user = $student->user;
        if (!$user) {
            return;
        }

        $account = $user->account;

        if ($account && $account->balance <= 0) {
            $this->promoteYearLevel($student);
            $this->assignNextPayables($student);
        }
    }

    protected function promoteYearLevel($student)
    {
        $levels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $currentIndex = array_search($student->year_level, $levels);

        if ($currentIndex !== false && $currentIndex < count($levels) - 1) {
            $student->year_level = $levels[$currentIndex + 1];
            $student->save();
        }
    }

    protected function assignNextPayables($student)
    {
        // find fees for the new year/semester
        $fees = \App\Models\Fee::where('year_level', $student->year_level)
            ->where('semester', '1st Sem')
            ->get();

        foreach ($fees as $fee) {
            $student->user->transactions()->create([
                'reference' => 'FEE-' . strtoupper($fee->name) . '-' . $student->id,
                'kind' => 'charge',
                'type' => $fee->name,
                'amount' => $fee->amount,
                'status' => 'pending',
                'meta' => ['description' => $fee->name],
            ]);
        }
    }
    public function download()
    {
        $transactions = Transaction::with('fee')->orderBy('created_at', 'desc')->get();

        $pdf = Pdf::loadView('pdf.transactions', [
            'transactions' => $transactions
        ]);

        return $pdf->download('transactions.pdf');
    }
}

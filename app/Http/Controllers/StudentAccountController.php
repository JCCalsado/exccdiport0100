<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\StudentPaymentTerm;
use App\Models\StudentAssessment;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\Student;

class StudentAccountController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        if (!$user->account) {
            $user->account()->create(['balance' => 0]);
        }

        // ✅ Get student by user_id, then use account_id
        $student = Student::where('user_id', $user->id)->first();
        
        if (!$student || !$student->account_id) {
            return back()->withErrors([
                'error' => 'Student profile not found or account_id missing.'
            ]);
        }

        $accountId = $student->account_id;

        // ✅ Get latest assessment by account_id
        $assessment = StudentAssessment::byAccountId($accountId)
            ->with('curriculum.program')
            ->where('status', 'active')
            ->latest()
            ->first();

        // ✅ Get payment terms by account_id
        $paymentTerms = StudentPaymentTerm::byAccountId($accountId)
            ->orderBy('term_order')
            ->get();

        // ✅ Get all transactions by account_id
        $transactions = Transaction::byAccountId($accountId)
            ->with('fee')
            ->orderBy('created_at', 'desc')
            ->get();

        // ✅ Get payment history by account_id
        $payments = Payment::byAccountId($accountId)
            ->orderBy('paid_at', 'desc')
            ->get();

        $totalScheduled = $paymentTerms->sum('amount');
        $totalPaid = $paymentTerms->sum('paid_amount');
        $remainingDue = $totalScheduled - $totalPaid;

        // Calculate stats
        $charges = $transactions->where('kind', 'charge')->sum('amount');
        $paymentsTotal = $transactions->where('kind', 'payment')
            ->where('status', 'paid')
            ->sum('amount');

        return Inertia::render('Student/AccountOverview', [
            'account_id' => $accountId, // ✅ Pass account_id to frontend
            'student' => [
                'id' => $student->id,
                'account_id' => $accountId, // ✅ Include in student data
                'student_id' => $student->student_id,
                'name' => $student->full_name,
                'email' => $student->email,
                'course' => $student->course,
                'year_level' => $student->year_level,
            ],
            'account' => [
                'id' => $user->account->id,
                'balance' => (float) $user->account->balance,
                'created_at' => $user->account->created_at?->toISOString(),
                'updated_at' => $user->account->updated_at?->toISOString(),
            ],
            'assessment' => $assessment ? [
                'id' => $assessment->id,
                'assessment_number' => $assessment->assessment_number,
                'school_year' => $assessment->school_year,
                'semester' => $assessment->semester,
                'year_level' => $assessment->year_level,
                'tuition_fee' => (float) $assessment->tuition_fee,
                'other_fees' => (float) $assessment->other_fees,
                'registration_fee' => (float) ($assessment->registration_fee ?? 0),
                'total_assessment' => (float) $assessment->total_assessment,
                'status' => $assessment->status,
                'subjects' => $assessment->subjects ?? [],
                'fee_breakdown' => $assessment->fee_breakdown ?? [],
                'curriculum' => $assessment->curriculum ? [
                    'id' => $assessment->curriculum->id,
                    'program' => [
                        'name' => $assessment->curriculum->program->name ?? 'N/A',
                        'major' => $assessment->curriculum->program->major ?? null,
                    ],
                ] : null,
            ] : null,
            'paymentTerms' => $paymentTerms->map(fn($term) => [
                'id' => $term->id,
                'term_name' => $term->term_name,
                'term_order' => $term->term_order,
                'amount' => (float) $term->amount,
                'paid_amount' => (float) $term->paid_amount,
                'remaining_balance' => (float) $term->remaining_balance,
                'due_date' => $term->due_date?->format('Y-m-d'),
                'status' => $term->status,
                'is_overdue' => $term->isOverdue(),
            ]),
            'transactions' => $transactions->map(fn($txn) => [
                'id' => $txn->id,
                'reference' => $txn->reference,
                'kind' => $txn->kind,
                'type' => $txn->type,
                'amount' => (float) $txn->amount,
                'status' => $txn->status,
                'payment_channel' => $txn->payment_channel,
                'paid_at' => $txn->paid_at?->toISOString(),
                'created_at' => $txn->created_at->toISOString(),
                'meta' => $txn->meta,
                'fee' => $txn->fee ? [
                    'id' => $txn->fee->id,
                    'name' => $txn->fee->name,
                    'category' => $txn->fee->category,
                ] : null,
            ]),
            'payments' => $payments->map(fn($payment) => [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'description' => $payment->description,
                'payment_method' => $payment->payment_method,
                'reference_number' => $payment->reference_number,
                'status' => $payment->status,
                'paid_at' => $payment->paid_at?->toISOString(),
            ]),
            'stats' => [
                'total_scheduled' => (float) $totalScheduled,
                'total_paid' => (float) $totalPaid,
                'remaining_due' => (float) $remainingDue,
                'total_charges' => (float) $charges,
                'total_payments' => (float) $paymentsTotal,
            ],
            'currentTerm' => [
                'year' => $assessment ? explode('-', $assessment->school_year)[0] : now()->year,
                'semester' => $assessment?->semester ?? '1st Sem',
            ],
        ]);
    }
}
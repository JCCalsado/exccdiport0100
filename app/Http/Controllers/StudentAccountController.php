<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\StudentPaymentTerm;
use App\Models\StudentAssessment;
use App\Models\Transaction;

class StudentAccountController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        if (!$user->account) {
            $user->account()->create(['balance' => 0]);
        }

        // Get latest assessment
        $assessment = StudentAssessment::where('user_id', $user->id)
            ->with('curriculum.program')
            ->where('status', 'active')
            ->latest()
            ->first();

        // Get payment terms
        $paymentTerms = StudentPaymentTerm::where('user_id', $user->id)
            ->orderBy('term_order')
            ->get();

        // Get payment history (actual transactions)
        $payments = Transaction::where('user_id', $user->id)
            ->where('kind', 'payment')
            ->orderBy('paid_at', 'desc')
            ->get();

        // Calculate stats
        $totalScheduled = $paymentTerms->sum('amount');
        $totalPaid = $paymentTerms->sum('paid_amount');
        $remainingDue = $totalScheduled - $totalPaid;

        return Inertia::render('Student/AccountOverview', [
            'student' => [
                'id' => $user->id,
                'student_id' => $user->student_id,
                'name' => $user->name,
                'email' => $user->email,
                'course' => $user->course,
                'year_level' => $user->year_level,
            ],
            'account' => $user->account,
            'assessment' => $assessment,
            'paymentTerms' => $paymentTerms->map(fn($term) => [
                'id' => $term->id,
                'term_name' => $term->term_name,
                'amount' => (float) $term->amount,
                'paid_amount' => (float) $term->paid_amount,
                'remaining_balance' => (float) $term->remaining_balance,
                'due_date' => $term->due_date?->format('Y-m-d'),
                'status' => $term->status,
                'is_overdue' => $term->due_date && $term->due_date->isPast() && !$term->isFullyPaid(),
            ]),
            'payments' => $payments,
            'stats' => [
                'total_scheduled' => (float) $totalScheduled,
                'total_paid' => (float) $totalPaid,
                'remaining_due' => (float) $remainingDue,
            ],
            'currentTerm' => [
                'year' => $assessment?->school_year ? explode('-', $assessment->school_year)[0] : now()->year,
                'semester' => $assessment?->semester ?? '1st Sem',
            ],
        ]);
    }
}
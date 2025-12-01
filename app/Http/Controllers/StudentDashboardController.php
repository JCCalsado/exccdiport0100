<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\StudentPaymentTerm;
use App\Models\Student;

class StudentDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->account) {
            $user->account()->create(['balance' => 0]);
        }

        // ✅ Get student by user_id, then use account_id
        $student = Student::where('user_id', $user->id)->first();
        
        if (!$student) {
            return back()->withErrors(['error' => 'Student profile not found.']);
        }

        $accountId = $student->account_id;

        // ✅ Get payment terms by account_id
        $paymentTerms = StudentPaymentTerm::byAccountId($accountId)
            ->orderBy('term_order')
            ->get()
            ->map(function ($term) {
                return [
                    'id' => $term->id,
                    'term_name' => $term->term_name,
                    'amount' => (float) $term->amount,
                    'paid_amount' => (float) $term->paid_amount,
                    'remaining_balance' => (float) $term->remaining_balance,
                    'due_date' => $term->due_date?->format('Y-m-d'),
                    'status' => $term->status,
                    'is_overdue' => $term->due_date && $term->due_date->isPast() && !$term->isFullyPaid(),
                ];
            });

        $totalScheduled = $paymentTerms->sum('amount');
        $totalPaid = $paymentTerms->sum('paid_amount');
        $remainingDue = $totalScheduled - $totalPaid;

        // ✅ Get transactions by account_id
        $transactions = Transaction::byAccountId($accountId)
            ->where('kind', 'payment')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($txn) {
                return [
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
                    'user' => $txn->user ? [
                        'id' => $txn->user->id,
                        'name' => $txn->user->name,
                        'email' => $txn->user->email,
                    ] : null,
                ];
            });

        // Get notifications
        $notifications = Notification::where(function ($q) use ($user) {
                $q->where('target_role', $user->role)
                ->orWhere('target_role', 'all');
            })
            ->orderByDesc('start_date')
            ->take(5)
            ->get()
            ->map(function ($n) {
                return [
                    'id' => $n->id,
                    'title' => $n->title,
                    'message' => $n->message,
                    'start_date' => $n->start_date?->toDateString(),
                    'end_date' => $n->end_date?->toDateString(),
                    'target_role' => $n->target_role,
                ];
            });

        return Inertia::render('Student/Dashboard', [
            'account_id' => $accountId, // ✅ Pass account_id to frontend
            'account' => [
                'id' => $user->account->id,
                'balance' => (float) $user->account->balance,
                'created_at' => $user->account->created_at?->toISOString(),
                'updated_at' => $user->account->updated_at?->toISOString(),
            ],
            'paymentTerms' => $paymentTerms,
            'notifications' => $notifications,
            'recentTransactions' => $transactions,
            'stats' => [
                'total_scheduled' => (float) $totalScheduled,
                'total_paid' => (float) $totalPaid,
                'remaining_due' => (float) $remainingDue,
                'upcoming_due_count' => $paymentTerms->where('status', 'pending')->count(),
            ],
        ]);
    }
}
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\StudentPaymentTerm;

class StudentDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->account) {
            $user->account()->create(['balance' => 0]);
        }

        // Get payment terms (what they WILL pay)
        $paymentTerms = StudentPaymentTerm::where('user_id', $user->id)
            ->orderBy('term_order')
            ->get();

        $totalScheduled = $paymentTerms->sum('amount');
        $totalPaid = $paymentTerms->sum('paid_amount');
        $remainingDue = $totalScheduled - $totalPaid;

        // Get actual transactions (recorded payments only)
        $transactions = Transaction::where('user_id', $user->id)
            ->where('kind', 'payment')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Get notifications
        $notifications = Notification::where(function ($q) use ($user) {
                $q->where('target_role', $user->role)
                  ->orWhere('target_role', 'all');
            })
            ->orderByDesc('start_date')
            ->take(5)
            ->get();

        return Inertia::render('Student/Dashboard', [
            'account' => $user->account,
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
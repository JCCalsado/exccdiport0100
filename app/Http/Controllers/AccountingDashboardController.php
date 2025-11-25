<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\StudentAssessment;
use App\Models\Fee;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class AccountingDashboardController extends Controller
{
    public function index()
    {
        $currentYear = now()->year;
        $currentSemester = now()->month >= 1 && now()->month <= 5 ? '2nd Sem' : '1st Sem';

        // Existing stats
        $stats = [
            'total_students' => User::where('role', 'student')->count(),
            'active_students' => User::where('role', 'student')
                ->where('status', User::STATUS_ACTIVE)
                ->count(),
            'total_charges' => Transaction::where('kind', 'charge')->sum('amount'),
            'total_payments' => Transaction::where('kind', 'payment')
                ->where('status', 'paid')
                ->sum('amount'),
            'total_pending' => abs(User::where('role', 'student')
                ->whereHas('account', function ($q) {
                    $q->where('balance', '>', 0);
                })
                ->with('account')
                ->get()
                ->sum('account.balance')),
            'collection_rate' => 0,
            'active_fees' => Fee::where('is_active', true)->count(),
            'total_fee_amount' => Fee::where('is_active', true)->sum('amount'),
        ];
        // Calculate collection rate
        $totalCharges = $stats['total_charges'];
        $totalPayments = $stats['total_payments'];
        $stats['collection_rate'] = $totalCharges > 0 
            ? round(($totalPayments / $totalCharges) * 100, 2) 
            : 0;

        // Students with balance
        $studentsWithBalance = User::where('role', 'student')
            ->whereHas('account', function ($q) {
                $q->where('balance', '>', 0);
            })
            ->with(['account', 'student'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'student_id' => $user->student_id,
                    'course' => $user->course,
                    'year_level' => $user->year_level,
                    'balance' => abs($user->account->balance ?? 0),
                ];
            });

        // Recent payments
        $recentPayments = Transaction::where('kind', 'payment')
            ->where('status', 'paid')
            ->with('user')
            ->orderBy('paid_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'student_name' => $transaction->user->name ?? 'N/A',
                    'amount' => $transaction->amount,
                    'status' => $transaction->status,
                    'paid_at' => $transaction->paid_at,
                    'created_at' => $transaction->created_at,
                ];
            });

        // Payment trends (last 6 months)
        $paymentTrends = Transaction::where('kind', 'payment')
            ->where('status', 'paid')
            ->where('paid_at', '>=', now()->subMonths(6))
            ->select(
                DB::raw('DATE_FORMAT(paid_at, "%Y-%m") as month'),
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        // Payment by method
        $paymentByMethod = Transaction::where('kind', 'payment')
            ->where('status', 'paid')
            ->whereNotNull('payment_channel')
            ->select(
                'payment_channel as method',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('payment_channel')
            ->orderBy('total', 'desc')
            ->get();

        // Students by year level
        $studentsByYearLevel = User::where('role', 'student')
            ->where('status', User::STATUS_ACTIVE)
            ->select('year_level', DB::raw('COUNT(*) as count'))
            ->groupBy('year_level')
            ->orderBy('year_level')
            ->get();

        // NEW: Student Fee Stats
        $studentFeeStats = [
            'total_assessments' => StudentAssessment::where('status', 'active')->count(),
            'total_assessment_amount' => StudentAssessment::where('status', 'active')
                ->sum('total_assessment'),
            'pending_assessments' => abs(User::where('role', 'student')
                ->whereHas('account', function ($q) {
                    $q->where('balance', '>', 0);
                })
                ->with('account')
                ->get()
                ->sum('account.balance')),
            'recent_assessments' => StudentAssessment::where('created_at', '>=', now()->subDays(30))
                ->count(),
            'recent_payments_amount' => Transaction::where('kind', 'payment')
                ->where('status', 'paid')
                ->where('paid_at', '>=', now()->subDays(30))
                ->sum('amount'),
        ];

        return Inertia::render('Accounting/Dashboard', [
            'stats' => $stats,
            'studentsWithBalance' => $studentsWithBalance,
            'recentPayments' => $recentPayments,
            'paymentTrends' => $paymentTrends,
            'paymentByMethod' => $paymentByMethod,
            'studentsByYearLevel' => $studentsByYearLevel,
            'currentTerm' => [
                'year' => $currentYear,
                'semester' => $currentSemester,
            ],
            'studentFeeStats' => $studentFeeStats, // NEW: Add student fee stats
        ]);
    }
}
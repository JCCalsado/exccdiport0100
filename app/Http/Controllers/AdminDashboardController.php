<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Student;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\StudentAssessment;
use App\Models\Fee;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index()
    {
        // ✅ System-wide stats
        $totalUsers = User::count();
        $totalStudents = Student::whereNotNull('account_id')->count();
        $activeStudents = Student::whereNotNull('account_id')
            ->where('status', 'enrolled')
            ->count();
        $graduatedStudents = Student::whereNotNull('account_id')
            ->where('status', 'graduated')
            ->count();
        
        // ✅ Financial overview using account_id
        $totalRevenue = Payment::whereNotNull('account_id')
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount');
        
        $totalOutstanding = DB::table('students')
            ->whereNotNull('account_id')
            ->sum('total_balance');
        
        $recentPayments30d = Payment::whereNotNull('account_id')
            ->where('created_at', '>=', now()->subDays(30))
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount');
        
        // ✅ Pending transactions
        $pendingCharges = Transaction::whereNotNull('account_id')
            ->where('kind', 'charge')
            ->where('status', 'pending')
            ->count();
        
        $pendingPayments = Transaction::whereNotNull('account_id')
            ->where('kind', 'payment')
            ->where('status', 'pending')
            ->count();
        
        // ✅ Recent activity with account_id
        $recentActivity = Transaction::with(['student' => function($query) {
                $query->select('id', 'account_id', 'student_id', 'first_name', 'last_name', 'middle_initial');
            }])
            ->whereNotNull('account_id')
            ->latest('created_at')
            ->take(15)
            ->get()
            ->map(function ($txn) {
                return [
                    'id' => $txn->id,
                    'account_id' => $txn->account_id, // ✅ PRIMARY
                    'reference' => $txn->reference,
                    'kind' => $txn->kind,
                    'type' => $txn->type,
                    'amount' => (float) $txn->amount,
                    'status' => $txn->status,
                    'created_at' => $txn->created_at->toISOString(),
                    'student' => $txn->student ? [
                        'account_id' => $txn->student->account_id,
                        'student_id' => $txn->student->student_id,
                        'name' => $txn->student->full_name,
                    ] : null,
                ];
            });
        
        // ✅ System health checks
        $studentsWithoutAccountId = Student::whereNull('account_id')->count();
        $transactionsWithoutAccountId = Transaction::whereNull('account_id')->count();
        $paymentsWithoutAccountId = Payment::whereNull('account_id')->count();
        $assessmentsWithoutAccountId = StudentAssessment::whereNull('account_id')->count();
        
        $systemHealth = [
            'students_missing_account_id' => $studentsWithoutAccountId,
            'transactions_missing_account_id' => $transactionsWithoutAccountId,
            'payments_missing_account_id' => $paymentsWithoutAccountId,
            'assessments_missing_account_id' => $assessmentsWithoutAccountId,
            'total_issues' => $studentsWithoutAccountId + $transactionsWithoutAccountId + 
                              $paymentsWithoutAccountId + $assessmentsWithoutAccountId,
        ];
        
        // ✅ Recent assessments
        $recentAssessments = StudentAssessment::with(['student', 'curriculum.program'])
            ->whereNotNull('account_id')
            ->where('status', 'active')
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(function ($assessment) {
                return [
                    'id' => $assessment->id,
                    'account_id' => $assessment->account_id, // ✅ PRIMARY
                    'assessment_number' => $assessment->assessment_number,
                    'total_assessment' => (float) $assessment->total_assessment,
                    'created_at' => $assessment->created_at->toISOString(),
                    'student' => $assessment->student ? [
                        'account_id' => $assessment->student->account_id,
                        'name' => $assessment->student->full_name,
                    ] : null,
                ];
            });
        
        // ✅ Resource counts
        $resourceStats = [
            'total_fees' => Fee::count(),
            'active_fees' => Fee::where('is_active', true)->count(),
            'total_subjects' => Subject::count(),
            'active_subjects' => Subject::where('is_active', true)->count(),
        ];
        
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_users' => $totalUsers,
                'total_students' => $totalStudents,
                'active_students' => $activeStudents,
                'graduated_students' => $graduatedStudents,
                'total_revenue' => (float) $totalRevenue,
                'total_outstanding' => (float) $totalOutstanding,
                'recent_payments_30d' => (float) $recentPayments30d,
                'pending_charges' => $pendingCharges,
                'pending_payments' => $pendingPayments,
            ],
            'recentActivity' => $recentActivity,
            'recentAssessments' => $recentAssessments,
            'resourceStats' => $resourceStats,
            'systemHealth' => $systemHealth,
        ]);
    }
}
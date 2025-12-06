<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Student;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\StudentAssessment;
use App\Models\StudentPaymentTerm;
use Illuminate\Support\Facades\DB;

class AccountingDashboardController extends Controller
{
    public function index(Request $request)
    {
        // ✅ Get overview stats using account_id
        $totalStudents = Student::whereNotNull('account_id')->count();
        $activeStudents = Student::whereNotNull('account_id')
            ->where('status', 'enrolled')
            ->count();
        
        // ✅ Financial stats using account_id
        $totalOutstanding = DB::table('students')
            ->whereNotNull('account_id')
            ->sum('total_balance');
        
        $recentPayments = Payment::whereNotNull('account_id')
            ->where('created_at', '>=', now()->subDays(30))
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount');
        
        // ✅ Pending charges by account_id
        $pendingCharges = Transaction::whereNotNull('account_id')
            ->where('kind', 'charge')
            ->where('status', 'pending')
            ->sum('amount');
        
        // ✅ Recent transactions with student info
        $recentTransactions = Transaction::with(['student' => function($query) {
                $query->select('id', 'account_id', 'student_id', 'first_name', 'last_name', 'middle_initial');
            }])
            ->whereNotNull('account_id')
            ->latest('created_at')
            ->take(10)
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
        
        // ✅ Students with overdue payments
        $overdueStudents = Student::whereNotNull('account_id')
            ->whereHas('paymentTerms', function($query) {
                $query->where('due_date', '<', now())
                    ->where('status', '!=', 'paid')
                    ->whereRaw('paid_amount < amount');
            })
            ->with(['paymentTerms' => function($query) {
                $query->where('due_date', '<', now())
                    ->where('status', '!=', 'paid')
                    ->whereRaw('paid_amount < amount')
                    ->orderBy('due_date');
            }])
            ->take(10)
            ->get()
            ->map(function ($student) {
                $overdueTerms = $student->paymentTerms;
                $totalOverdue = $overdueTerms->sum(fn($term) => $term->amount - $term->paid_amount);
                
                return [
                    'account_id' => $student->account_id, // ✅ PRIMARY
                    'student_id' => $student->student_id,
                    'name' => $student->full_name,
                    'course' => $student->course,
                    'year_level' => $student->year_level,
                    'total_overdue' => (float) $totalOverdue,
                    'overdue_terms_count' => $overdueTerms->count(),
                    'oldest_due_date' => $overdueTerms->first()?->due_date?->format('Y-m-d'),
                ];
            });
        
        // ✅ Recent assessments with account_id
        $recentAssessments = StudentAssessment::with(['student' => function($query) {
                $query->select('id', 'account_id', 'student_id', 'first_name', 'last_name', 'middle_initial');
            }, 'curriculum.program'])
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
                    'school_year' => $assessment->school_year,
                    'semester' => $assessment->semester,
                    'total_assessment' => (float) $assessment->total_assessment,
                    'created_at' => $assessment->created_at->toISOString(),
                    'student' => $assessment->student ? [
                        'account_id' => $assessment->student->account_id,
                        'student_id' => $assessment->student->student_id,
                        'name' => $assessment->student->full_name,
                    ] : null,
                    'curriculum' => $assessment->curriculum ? [
                        'program' => $assessment->curriculum->program->full_name ?? 'N/A',
                    ] : null,
                ];
            });
        
        return Inertia::render('Accounting/Dashboard', [
            'stats' => [
                'total_students' => $totalStudents,
                'active_students' => $activeStudents,
                'total_outstanding' => (float) $totalOutstanding,
                'recent_payments_30d' => (float) $recentPayments,
                'pending_charges' => (float) $pendingCharges,
                'overdue_count' => $overdueStudents->count(),
            ],
            'recentTransactions' => $recentTransactions,
            'overdueStudents' => $overdueStudents,
            'recentAssessments' => $recentAssessments,
        ]);
    }
}
<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\StudentAssessment;
use App\Models\Transaction;
use App\Models\Payment;

class StudentAccountController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        // Ensure account exists
        if (!$user->account) {
            $user->account()->create(['balance' => 0]);
        }

        // Load user with necessary relationships
        $user->load(['transactions' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }]);

        // Get latest active assessment
        $latestAssessment = StudentAssessment::where('user_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        // Get all transactions with fee relation
        $transactions = Transaction::where('user_id', $user->id)
            ->with('fee')
            ->orderBy('created_at', 'desc')
            ->get();

        // Get student payments if student model exists
        $payments = collect();
        if ($user->student) {
            $payments = Payment::where('student_id', $user->student->id)
                ->orderBy('paid_at', 'desc')
                ->get();
        }

        // Build fee breakdown from transactions
        $feeBreakdown = $transactions->where('kind', 'charge')
            ->groupBy('type')
            ->map(function ($group) {
                return [
                    'category' => $group->first()->type,
                    'total' => $group->sum('amount'),
                    'items' => $group->count(),
                ];
            })->values();

        // Prepare assessment data
        $assessment = null;
        $assessmentLines = [];
        $termsOfPayment = null;

        if ($latestAssessment) {
            $assessment = [
                'id' => $latestAssessment->id,
                'assessment_number' => $latestAssessment->assessment_number,
                'school_year' => $latestAssessment->school_year,
                'semester' => $latestAssessment->semester,
                'year_level' => $latestAssessment->year_level,
                'status' => $latestAssessment->status,
                'tuition_fee' => $latestAssessment->tuition_fee,
                'other_fees' => $latestAssessment->other_fees,
                'registration_fee' => $latestAssessment->registration_fee ?? 0,
                'total_assessment' => $latestAssessment->total_assessment,
                'subjects' => $latestAssessment->subjects,
                'total_units' => collect($latestAssessment->subjects ?? [])->sum('units'),
                'created_at' => $latestAssessment->created_at,
            ];

            $assessmentLines = $latestAssessment->subjects ?? [];
            $termsOfPayment = $latestAssessment->payment_terms;
        }

        // Build fees list from transactions
        $fees = $transactions->where('kind', 'charge')->map(function ($t) {
            return [
                'id' => $t->fee_id ?? null,
                'name' => $t->meta['fee_name'] ?? ($t->fee->name ?? ($t->type ?? 'Fee')),
                'amount' => $t->amount,
                'category' => $t->type ?? 'Other',
            ];
        })->values();

        // Determine current term
        $year = now()->year;
        $month = now()->month;

        if ($month >= 6 && $month <= 10) {
            $semester = '1st Sem';
        } elseif ($month >= 11 || $month <= 3) {
            $semester = '2nd Sem';
        } else {
            $semester = 'Summer';
        }

        $currentTerm = [
            'year' => $latestAssessment->year ?? $year,
            'semester' => $latestAssessment->semester ?? $semester,
        ];

        // Calculate statistics
        $totalCharges = $transactions->where('kind', 'charge')->sum('amount');
        $totalPayments = $transactions->where('kind', 'payment')
            ->where('status', 'paid')
            ->sum('amount');
        $remainingBalance = max(0, $totalCharges - $totalPayments);
        $pendingChargesCount = $transactions->where('kind', 'charge')
            ->where('status', 'pending')
            ->count();

        return Inertia::render('Student/AccountOverview', [
            'student' => $user,
            'account' => $user->account,
            'assessment' => $assessment,
            'assessmentLines' => $assessmentLines,
            'termsOfPayment' => $termsOfPayment,
            'transactions' => $transactions,
            'fees' => $fees,
            'currentTerm' => $currentTerm,
            'tab' => request('tab', 'fees'),
            'stats' => [
                'total_fees' => (float) $totalCharges,
                'total_paid' => (float) $totalPayments,
                'remaining_balance' => (float) $remainingBalance,
                'pending_charges_count' => $pendingChargesCount,
            ],
            'feeBreakdown' => $feeBreakdown,
        ]);
    }
}
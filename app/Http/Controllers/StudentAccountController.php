<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Fee;

class StudentAccountController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        // Ensure account exists
        if (!$user->account) {
            $user->account()->create(['balance' => 0]);
        }

        // Load transactions (via user, not account)
        $user->load(['transactions' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }]);

        $student = $user;
        $account = $user->account;
        $transactions = $user->transactions;

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
            'year' => $year,
            'semester' => $semester,
        ];

        // Load Fee List
        $fees = Fee::active()
            ->where('year_level', $user->year_level)
            ->where('semester', $semester)
            ->where('school_year', $year . '-' . ($year + 1))
            ->select('name', 'amount', 'category')
            ->get();

        if ($fees->isEmpty()) {
            $fees = collect([
                ['name' => 'Registration Fee', 'amount' => 200.0, 'category' => 'Miscellaneous'],
                ['name' => 'Tuition Fee', 'amount' => 5000.0, 'category' => 'Tuition'],
                ['name' => 'Lab Fee', 'amount' => 2000.0, 'category' => 'Laboratory'],
                ['name' => 'Library Fee', 'amount' => 500.0, 'category' => 'Library'],
                ['name' => 'Misc. Fee', 'amount' => 1200.0, 'category' => 'Miscellaneous'],
            ]);
        }

        // ----- STATS -----
        $total_fees = $fees->sum('amount');

        $total_paid = $transactions
            ->where('kind', 'payment')
            ->where('status', 'paid')
            ->sum('amount');

        $remaining_balance = max(0, $total_fees - $total_paid);

        $pending_charges_count = $transactions
            ->where('kind', 'charge')
            ->where('status', 'pending')
            ->count();

        return Inertia::render('Student/AccountOverview', [
            'student' => $student,
            'account' => $account,
            'assessment' => null,
            'assessmentLines' => [],
            'termsOfPayment' => null,
            'transactions' => $transactions,
            'fees' => $fees,
            'currentTerm' => $currentTerm,
            'tab' => request('tab'),
            'stats' => [
                'total_fees' => $total_fees,
                'total_paid' => $total_paid,
                'remaining_balance' => $remaining_balance,
                'pending_charges_count' => $pending_charges_count,
            ],
        ]);
    }
}
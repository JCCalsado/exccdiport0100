<?php

namespace App\Services;

use App\Models\User;

class AccountService
{
    /**
     * Recalculate a user's balance based on transactions.
     */
    public static function recalculate(?User $user): void
    {
        if (!$user) {
            return;
        }

        $charges = $user->transactions()
            ->where('kind', 'charge')
            ->sum('amount');

        $payments = $user->transactions()
            ->where('kind', 'payment')
            ->where('status', 'paid')
            ->sum('amount');

        $balance = $charges - $payments;

        // Ensure account exists
        $account = $user->account ?? $user->account()->create(['balance' => 0]);
        $account->update(['balance' => $balance]);

        // Update student if available - ADD NULL CHECK
        if ($user->student) {
            $user->student->update(['total_balance' => abs($balance)]);

            // Auto-promote when balance is cleared
            if ($balance <= 0) {
                self::promoteStudent($user);
            }
        }
    }

    /**
     * Promote student to next year level when balance = 0
     */
    protected static function promoteStudent(User $user): void
    {
        // ✅ Only promote at end of school year
        $currentMonth = now()->month;
        
        // May-June is end of school year
        if ($currentMonth < 5 || $currentMonth > 6) {
            // Not promotion season
            return;
        }
        
        $student = $user->student;
        if (!$student) return;
        
        // ✅ Check if current year assessment is fully paid
        $currentYearAssessment = StudentAssessment::where('user_id', $user->id)
            ->where('year_level', $student->year_level)
            ->where('school_year', now()->year . '-' . (now()->year + 1))
            ->where('status', 'active')
            ->first();
        
        if (!$currentYearAssessment) return;
        
        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $currentIndex = array_search($student->year_level, $yearLevels);

        if ($currentIndex !== false && $currentIndex < count($yearLevels) - 1) {
            $student->update([
                'year_level' => $yearLevels[$currentIndex + 1],
            ]);
            
            \Log::info('Student promoted', [
                'user_id' => $user->id,
                'from' => $yearLevels[$currentIndex],
                'to' => $yearLevels[$currentIndex + 1],
            ]);
        } elseif ($currentIndex === count($yearLevels) - 1) {
            $student->update(['status' => 'graduated']);
            $user->update(['status' => User::STATUS_GRADUATED]);
            
            \Log::info('Student graduated', ['user_id' => $user->id]);
        }
    }
}
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
        $student = $user->student;

        $yearLevels = [
            '1st Year',
            '2nd Year',
            '3rd Year',
            '4th Year',
        ];

        $currentIndex = array_search($student->year_level, $yearLevels);

        if ($currentIndex !== false && $currentIndex < count($yearLevels) - 1) {
            // ✅ Promote to next year
            $student->update([
                'year_level' => $yearLevels[$currentIndex + 1],
            ]);
        } elseif ($currentIndex === count($yearLevels) - 1) {
            // ✅ Graduate if last year is completed
            $student->update([
                'status' => 'graduated',
            ]);
        }
    }
}
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Generate account_id for students without one
        $studentsWithoutAccountId = DB::table('students')
            ->whereNull('account_id')
            ->orderBy('id')
            ->get();

        if ($studentsWithoutAccountId->isEmpty()) {
            return; // All students already have account_id
        }

        $date = now()->format('Ymd');
        $counter = $this->getNextAccountCounter($date);
        $generated = 0;

        foreach ($studentsWithoutAccountId as $student) {
            $accountId = "ACC-{$date}-" . str_pad($counter, 4, '0', STR_PAD_LEFT);
            
            // Ensure uniqueness
            while (DB::table('students')->where('account_id', $accountId)->exists()) {
                $counter++;
                $accountId = "ACC-{$date}-" . str_pad($counter, 4, '0', STR_PAD_LEFT);
            }
            
            DB::table('students')
                ->where('id', $student->id)
                ->update(['account_id' => $accountId]);
            
            $counter++;
            $generated++;
        }

        // Step 2: Backfill student_payment_terms
        $this->backfillPaymentTerms();

        // Step 3: Backfill student_assessments
        $this->backfillAssessments();

        // Step 4: Backfill transactions
        $this->backfillTransactions();

        // Step 5: Backfill payments
        $this->backfillPayments();
    }

    public function down(): void
    {
        // Cannot reverse data backfill
    }

    protected function backfillPaymentTerms(): void
    {
        if (!Schema::hasTable('student_payment_terms')) {
            return;
        }

        $count = DB::table('student_payment_terms')
            ->whereNull('account_id')
            ->count();

        if ($count === 0) {
            return;
        }

        DB::statement("
            UPDATE student_payment_terms spt
            INNER JOIN students s ON spt.user_id = s.user_id
            SET spt.account_id = s.account_id
            WHERE spt.account_id IS NULL
        ");
    }

    protected function backfillAssessments(): void
    {
        if (!Schema::hasTable('student_assessments')) {
            return;
        }

        $count = DB::table('student_assessments')
            ->whereNull('account_id')
            ->count();

        if ($count === 0) {
            return;
        }

        DB::statement("
            UPDATE student_assessments sa
            INNER JOIN students s ON sa.user_id = s.user_id
            SET sa.account_id = s.account_id
            WHERE sa.account_id IS NULL
        ");
    }

    protected function backfillTransactions(): void
    {
        if (!Schema::hasTable('transactions')) {
            return;
        }

        $count = DB::table('transactions')
            ->whereNull('account_id')
            ->count();

        if ($count === 0) {
            return;
        }

        DB::statement("
            UPDATE transactions t
            INNER JOIN students s ON t.user_id = s.user_id
            SET t.account_id = s.account_id
            WHERE t.account_id IS NULL
        ");
    }

    protected function backfillPayments(): void
    {
        if (!Schema::hasTable('payments')) {
            return;
        }

        $count = DB::table('payments')
            ->whereNull('account_id')
            ->count();

        if ($count === 0) {
            return;
        }

        DB::statement("
            UPDATE payments p
            INNER JOIN students s ON p.student_id = s.id
            SET p.account_id = s.account_id
            WHERE p.account_id IS NULL
        ");
    }

    protected function getNextAccountCounter(string $date): int
    {
        $prefix = "ACC-{$date}-";
        
        $lastStudent = DB::table('students')
            ->where('account_id', 'like', "{$prefix}%")
            ->orderByRaw('CAST(SUBSTRING(account_id, 14) AS UNSIGNED) DESC')
            ->first();

        if ($lastStudent) {
            return intval(substr($lastStudent->account_id, -4)) + 1;
        }

        return 1;
    }
};
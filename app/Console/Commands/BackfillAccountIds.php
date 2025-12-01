<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Student;
use App\Models\StudentPaymentTerm;
use App\Models\StudentAssessment;
use App\Models\Transaction;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class BackfillAccountIds extends Command
{
    protected $signature = 'students:backfill-account-ids 
                            {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Backfill account_id for all student financial records';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        try {
            DB::beginTransaction();

            // Step 1: Generate account_ids for students without one
            $this->generateMissingAccountIds($dryRun);

            // Step 2: Backfill payment terms
            $this->backfillPaymentTerms($dryRun);

            // Step 3: Backfill assessments
            $this->backfillAssessments($dryRun);

            // Step 4: Backfill transactions
            $this->backfillTransactions($dryRun);

            // Step 5: Backfill payments
            $this->backfillPayments($dryRun);

            if (!$dryRun) {
                DB::commit();
                $this->info('✅ Backfill completed successfully!');
            } else {
                DB::rollBack();
                $this->info('✅ Dry run completed - no changes made');
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    protected function generateMissingAccountIds(bool $dryRun): void
    {
        $students = Student::whereNull('account_id')->get();
        
        if ($students->isEmpty()) {
            $this->info('✓ All students already have account_id');
            return;
        }

        $this->info("📋 Generating account_id for {$students->count()} students...");
        
        $date = now()->format('Ymd');
        $counter = $this->getNextAccountCounter($date);
        
        foreach ($students as $student) {
            $accountId = "ACC-{$date}-" . str_pad($counter, 4, '0', STR_PAD_LEFT);
            
            while (Student::where('account_id', $accountId)->exists()) {
                $counter++;
                $accountId = "ACC-{$date}-" . str_pad($counter, 4, '0', STR_PAD_LEFT);
            }
            
            if (!$dryRun) {
                $student->update(['account_id' => $accountId]);
            }
            
            $this->info("  ✓ {$accountId} → {$student->first_name} {$student->last_name}");
            $counter++;
        }
    }

    protected function backfillPaymentTerms(bool $dryRun): void
    {
        $count = StudentPaymentTerm::whereNull('account_id')
            ->whereHas('user.student')
            ->count();

        if ($count === 0) {
            $this->info('✓ All payment terms already have account_id');
            return;
        }

        $this->info("📋 Backfilling {$count} payment terms...");

        if (!$dryRun) {
            DB::statement("
                UPDATE student_payment_terms spt
                INNER JOIN students s ON spt.user_id = s.user_id
                SET spt.account_id = s.account_id
                WHERE spt.account_id IS NULL
            ");
        }

        $this->info("  ✓ Updated {$count} payment terms");
    }

    protected function backfillAssessments(bool $dryRun): void
    {
        $count = StudentAssessment::whereNull('account_id')
            ->whereHas('user.student')
            ->count();

        if ($count === 0) {
            $this->info('✓ All assessments already have account_id');
            return;
        }

        $this->info("📋 Backfilling {$count} assessments...");

        if (!$dryRun) {
            DB::statement("
                UPDATE student_assessments sa
                INNER JOIN students s ON sa.user_id = s.user_id
                SET sa.account_id = s.account_id
                WHERE sa.account_id IS NULL
            ");
        }

        $this->info("  ✓ Updated {$count} assessments");
    }

    protected function backfillTransactions(bool $dryRun): void
    {
        $count = Transaction::whereNull('account_id_student')
            ->whereHas('user.student')
            ->count();

        if ($count === 0) {
            $this->info('✓ All transactions already have account_id_student');
            return;
        }

        $this->info("📋 Backfilling {$count} transactions...");

        if (!$dryRun) {
            DB::statement("
                UPDATE transactions t
                INNER JOIN students s ON t.user_id = s.user_id
                SET t.account_id_student = s.account_id
                WHERE t.account_id_student IS NULL
            ");
        }

        $this->info("  ✓ Updated {$count} transactions");
    }

    protected function backfillPayments(bool $dryRun): void
    {
        $count = Payment::whereNull('account_id')
            ->whereHas('student')
            ->count();

        if ($count === 0) {
            $this->info('✓ All payments already have account_id');
            return;
        }

        $this->info("📋 Backfilling {$count} payments...");

        if (!$dryRun) {
            DB::statement("
                UPDATE payments p
                INNER JOIN students s ON p.student_id = s.id
                SET p.account_id = s.account_id
                WHERE p.account_id IS NULL
            ");
        }

        $this->info("  ✓ Updated {$count} payments");
    }

    protected function getNextAccountCounter(string $date): int
    {
        $prefix = "ACC-{$date}-";
        $lastStudent = Student::where('account_id', 'like', "{$prefix}%")
            ->orderByRaw('CAST(SUBSTRING(account_id, 14) AS UNSIGNED) DESC')
            ->first();

        if ($lastStudent) {
            return intval(substr($lastStudent->account_id, -4)) + 1;
        }

        return 1;
    }
}
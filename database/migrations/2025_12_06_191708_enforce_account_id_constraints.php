<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Verify all students have account_id
        $missingCount = DB::table('students')->whereNull('account_id')->count();
        
        if ($missingCount > 0) {
            throw new \Exception(
                "Cannot enforce constraints: {$missingCount} students still missing account_id."
            );
        }

        // Step 2: FORCE DROP all incorrect foreign keys using raw SQL
        $this->forceDropForeignKeys();

        // Step 3: Make account_id NOT NULL on students table
        Schema::table('students', function (Blueprint $table) {
            $table->string('account_id', 50)->nullable(false)->change();
        });

        // Step 4: Make account_id NOT NULL on financial tables
        $tables = ['student_payment_terms', 'student_assessments', 'transactions', 'payments'];
        
        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            $missingInTable = DB::table($tableName)->whereNull('account_id')->count();
            
            if ($missingInTable > 0) {
                continue; // Skip if data incomplete
            }

            if (!Schema::hasColumn($tableName, 'account_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->string('account_id', 50)->nullable(false)->change();
            });
        }

        // Step 5: Add proper indexes
        $this->addProperIndexes();
    }

    protected function forceDropForeignKeys(): void
    {
        $database = DB::getDatabaseName();
        
        // Get all foreign keys that reference account_id
        $foreignKeys = DB::select("
            SELECT 
                TABLE_NAME,
                CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE 
                CONSTRAINT_SCHEMA = ?
                AND COLUMN_NAME = 'account_id'
                AND CONSTRAINT_NAME != 'PRIMARY'
                AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [$database]);

        // Drop each foreign key
        foreach ($foreignKeys as $fk) {
            try {
                DB::statement("ALTER TABLE `{$fk->TABLE_NAME}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            } catch (\Exception $e) {
                // Continue if already dropped
            }
        }

        // Also try specific known foreign keys
        $knownForeignKeys = [
            'transactions' => 'transactions_account_id_foreign',
            'payments' => 'payments_account_id_foreign',
            'student_payment_terms' => 'student_payment_terms_account_id_foreign',
            'student_assessments' => 'student_assessments_account_id_foreign',
        ];

        foreach ($knownForeignKeys as $table => $foreignKey) {
            try {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$foreignKey}`");
            } catch (\Exception $e) {
                // Continue if doesn't exist
            }
        }
    }

    protected function addProperIndexes(): void
    {
        $tables = ['transactions', 'payments', 'student_payment_terms', 'student_assessments'];
        
        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            if (!Schema::hasColumn($tableName, 'account_id')) {
                continue;
            }

            // Drop existing index if exists
            try {
                DB::statement("ALTER TABLE `{$tableName}` DROP INDEX `{$tableName}_account_id_index`");
            } catch (\Exception $e) {
                // Doesn't exist, continue
            }

            // Add new index
            try {
                DB::statement("CREATE INDEX `{$tableName}_account_id_index` ON `{$tableName}` (`account_id`)");
            } catch (\Exception $e) {
                // Already exists, continue
            }
        }
    }

    public function down(): void
    {
        $tables = ['students', 'student_payment_terms', 'student_assessments', 'transactions', 'payments'];
        
        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            if (!Schema::hasColumn($tableName, 'account_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->string('account_id', 50)->nullable()->change();
            });
        }
    }
};
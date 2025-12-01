<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Add account_id column
            $table->string('account_id', 50)->nullable()->after('id');
        });

        // Generate account_id for existing students
        $this->generateAccountIds();

        // Make account_id non-nullable and add unique constraint
        Schema::table('students', function (Blueprint $table) {
            $table->string('account_id', 50)->nullable(false)->change();
            $table->unique('account_id');
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['account_id']);
            $table->dropIndex(['account_id']);
            $table->dropColumn('account_id');
        });
    }

    /**
     * Generate account_id for all existing students
     */
    protected function generateAccountIds(): void
    {
        $students = DB::table('students')->whereNull('account_id')->get();

        foreach ($students as $student) {
            $accountId = $this->generateUniqueAccountId($student->created_at);
            
            DB::table('students')
                ->where('id', $student->id)
                ->update(['account_id' => $accountId]);
        }
    }

    /**
     * Generate unique account ID in format: ACC-YYYYMMDD-XXXX
     */
    protected function generateUniqueAccountId($createdAt): string
    {
        $date = date('Ymd', strtotime($createdAt));
        $attempts = 0;
        $maxAttempts = 9999;

        do {
            $sequential = str_pad(rand(1, $maxAttempts), 4, '0', STR_PAD_LEFT);
            $accountId = "ACC-{$date}-{$sequential}";
            
            $exists = DB::table('students')
                ->where('account_id', $accountId)
                ->exists();
            
            $attempts++;
            
            if ($attempts > 100) {
                // Fallback to timestamp-based unique ID
                $accountId = "ACC-{$date}-" . substr(md5(microtime()), 0, 4);
                break;
            }
        } while ($exists);

        return $accountId;
    }
};
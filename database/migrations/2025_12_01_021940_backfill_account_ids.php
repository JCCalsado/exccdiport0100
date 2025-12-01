<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Student;

return new class extends Migration
{
    public function up(): void
    {
        // Generate account_id for all students
        $students = Student::whereNull('account_id')->get();
        
        foreach ($students as $student) {
            $accountId = $this->generateAccountId();
            $student->update(['account_id' => $accountId]);
            
            // Update related records
            DB::table('student_payment_terms')
                ->where('user_id', $student->user_id)
                ->update(['account_id' => $accountId]);
            
            DB::table('student_assessments')
                ->where('user_id', $student->user_id)
                ->update(['account_id' => $accountId]);
            
            DB::table('transactions')
                ->where('user_id', $student->user_id)
                ->update(['account_id' => $accountId]);
            
            DB::table('payments')
                ->where('student_id', $student->id)
                ->update(['account_id' => $accountId]);
        }
    }

    public function down(): void
    {
        // Cannot reverse backfill
    }

    private function generateAccountId(): string
    {
        do {
            $date = now()->format('Ymd');
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $accountId = "ACC-{$date}-{$random}";
        } while (DB::table('students')->where('account_id', $accountId)->exists());

        return $accountId;
    }
};
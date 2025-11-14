<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting comprehensive database seeding...');
        $this->command->newLine();

        // Clear existing data (optional - comment out if you want to preserve data)
        $this->command->info('🗑️  Clearing existing data...');
        DB::table('payments')->delete();
        DB::table('transactions')->delete();
        DB::table('student_assessments')->delete();
        DB::table('students')->delete();
        DB::table('accounts')->delete();
        DB::table('subjects')->delete();
        DB::table('fees')->delete();
        DB::table('notifications')->delete();
        // Note: We don't delete users here as ComprehensiveUserSeeder handles it
        
        $this->command->info('✓ Existing data cleared');
        $this->command->newLine();

        // Seed in correct order
        $this->command->info('📚 Step 1: Seeding Users (Admin, Accounting, 100 Students)...');
        $this->call(ComprehensiveUserSeeder::class);
        $this->command->newLine();

        $this->command->info('📖 Step 2: Seeding Subjects (OBE Curriculum)...');
        $this->call(EnhancedSubjectSeeder::class);
        $this->command->newLine();

        $this->command->info('💰 Step 3: Seeding Fees...');
        $this->call(FeeSeeder::class);
        $this->command->newLine();

        $this->command->info('📋 Step 4: Creating Student Assessments & Transactions...');
        $this->call(ComprehensiveAssessmentSeeder::class);
        $this->command->newLine();

        $this->command->info('🔔 Step 5: Seeding Notifications...');
        $this->call(NotificationSeeder::class);
        $this->command->newLine();

        $this->command->info('✅ Database seeding completed successfully!');
        $this->command->newLine();
        
        $this->displaySummary();
    }

    private function displaySummary(): void
    {
        $this->command->info('📊 SEEDING SUMMARY');
        $this->command->info('═══════════════════════════════════════════════════════');
        
        $userCount = \App\Models\User::count();
        $adminCount = \App\Models\User::where('role', 'admin')->count();
        $accountingCount = \App\Models\User::where('role', 'accounting')->count();
        $studentCount = \App\Models\User::where('role', 'student')->count();
        
        $activeStudents = \App\Models\User::where('role', 'student')
            ->where('status', \App\Models\User::STATUS_ACTIVE)->count();
        $droppedStudents = \App\Models\User::where('role', 'student')
            ->where('status', \App\Models\User::STATUS_DROPPED)->count();
        $graduatedStudents = \App\Models\User::where('role', 'student')
            ->where('status', \App\Models\User::STATUS_GRADUATED)->count();
        
        $firstYear = \App\Models\User::where('role', 'student')
            ->where('year_level', '1st Year')->count();
        $secondYear = \App\Models\User::where('role', 'student')
            ->where('year_level', '2nd Year')->count();
        $fourthYear = \App\Models\User::where('role', 'student')
            ->where('year_level', '4th Year')->count();
        
        $subjectCount = \App\Models\Subject::count();
        $feeCount = \App\Models\Fee::count();
        $assessmentCount = \App\Models\StudentAssessment::count();
        $transactionCount = \App\Models\Transaction::count();
        $paymentCount = \App\Models\Payment::count();
        
        $this->command->table(
            ['Category', 'Count'],
            [
                ['Total Users', $userCount],
                ['├─ Admins', $adminCount],
                ['├─ Accounting Staff', $accountingCount],
                ['└─ Students', $studentCount],
                ['', ''],
                ['Student Status Distribution', ''],
                ['├─ Active', $activeStudents],
                ['├─ Dropped', $droppedStudents],
                ['└─ Graduated', $graduatedStudents],
                ['', ''],
                ['Year Level Distribution', ''],
                ['├─ 1st Year', $firstYear],
                ['├─ 2nd Year', $secondYear],
                ['└─ 4th Year', $fourthYear],
                ['', ''],
                ['Academic Data', ''],
                ['├─ Subjects', $subjectCount],
                ['├─ Fees', $feeCount],
                ['├─ Student Assessments', $assessmentCount],
                ['├─ Transactions', $transactionCount],
                ['└─ Payment Records', $paymentCount],
            ]
        );
        
        $this->command->newLine();
        $this->command->info('🔐 DEFAULT CREDENTIALS');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin', 'admin@ccdi.edu.ph', 'password'],
                ['Accounting', 'accounting@ccdi.edu.ph', 'password'],
                ['Students', 'student1@ccdi.edu.ph to student100@ccdi.edu.ph', 'password'],
            ]
        );
        
        $this->command->newLine();
        $this->command->info('💡 TIPS');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('• All students have complete assessments and transactions');
        $this->command->info('• Students with balances have payment history');
        $this->command->info('• Graduated students (4th year) have zero balance');
        $this->command->info('• Run: php artisan db:seed --class=DatabaseSeeder to re-seed');
        $this->command->newLine();
    }
}
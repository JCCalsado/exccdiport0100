<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚀 Starting clean database setup...');
        
        // Core system data (users, programs, curriculum)
        $this->call([
            OBECurriculumSeeder::class,
            ComprehensiveUserSeeder::class,
            EnhancedSubjectSeeder::class,
            FeeSeeder::class,
            NotificationSeeder::class,
            StudentPaymentTermsSeeder::class,
        ]);
        
        $this->command->info('✅ Clean system setup completed!');
        $this->command->warn('⚠️  No financial data generated - all accounts start at ₱0.00');
    }
}
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_assessments', function (Blueprint $table) {
            $table->foreignId('curriculum_id')->nullable()->after('user_id')->constrained()->onDelete('set null');
            $table->decimal('registration_fee', 12, 2)->default(0)->after('other_fees');
            $table->json('payment_terms')->nullable()->after('fee_breakdown'); // Store term breakdown
        });
    }

    public function down(): void
    {
        Schema::table('student_assessments', function (Blueprint $table) {
            $table->dropForeign(['curriculum_id']);
            $table->dropColumn(['curriculum_id', 'registration_fee', 'payment_terms']);
        });
    }
};
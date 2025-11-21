<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curricula', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->onDelete('cascade');
            $table->string('school_year'); // e.g., "2025-2026"
            $table->string('year_level'); // e.g., "1st Year"
            $table->string('semester'); // e.g., "1st Sem"
            $table->decimal('tuition_per_unit', 10, 2); // From document
            $table->decimal('lab_fee', 10, 2)->default(0); // From document
            $table->decimal('registration_fee', 10, 2)->default(0);
            $table->decimal('misc_fee', 10, 2)->default(0);
            $table->integer('term_count')->default(5); // Registration, Prelim, Midterm, Semi-Final, Final
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['program_id', 'school_year', 'year_level', 'semester'], 'curriculum_unique');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curricula');
    }
};
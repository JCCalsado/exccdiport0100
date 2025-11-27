<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_payment_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('curriculum_id')->nullable()->constrained()->onDelete('set null');
            $table->string('school_year');
            $table->string('semester');
            $table->string('term_name'); // 'Registration', 'Prelim', 'Midterm', etc.
            $table->integer('term_order')->default(1);
            $table->decimal('amount', 12, 2);
            $table->date('due_date')->nullable();
            $table->enum('status', ['pending', 'paid', 'partial'])->default('pending');
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->timestamps();
            
            $table->index(['user_id', 'school_year', 'semester']);
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_payment_terms');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->onDelete('cascade');
            $table->string('code', 20)->unique(); // e.g., "GE1"
            $table->string('title'); // e.g., "Purposive Communication"
            $table->text('description')->nullable();
            $table->decimal('lec_units', 4, 2)->default(0); // Lecture units
            $table->decimal('lab_units', 4, 2)->default(0); // Lab units
            $table->decimal('total_units', 4, 2); // Total units
            $table->boolean('has_lab')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['program_id', 'code']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
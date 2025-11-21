<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_curricula', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('curriculum_id')->constrained()->onDelete('cascade');
            $table->string('enrollment_status')->default('active'); // active, completed, dropped
            $table->date('enrolled_at');
            $table->date('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'enrollment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_curricula');
    }
};
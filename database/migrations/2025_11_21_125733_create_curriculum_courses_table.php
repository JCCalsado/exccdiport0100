<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->integer('order')->default(0); // Display order
            $table->timestamps();
            
            $table->unique(['curriculum_id', 'course_id']);
            $table->index('order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_courses');
    }
};
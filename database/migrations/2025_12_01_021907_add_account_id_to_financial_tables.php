<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add account_id to student_payment_terms
        Schema::table('student_payment_terms', function (Blueprint $table) {
            $table->string('account_id', 50)->nullable()->after('user_id')->index();
        });

        // Add account_id to student_assessments
        Schema::table('student_assessments', function (Blueprint $table) {
            $table->string('account_id', 50)->nullable()->after('user_id')->index();
        });

        // Add account_id to transactions
        if (!Schema::hasColumn('transactions', 'account_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('account_id', 50)->nullable()->after('user_id')->index();
            });
        }

        // Add account_id to payments
        Schema::table('payments', function (Blueprint $table) {
            $table->string('account_id', 50)->nullable()->after('student_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('student_payment_terms', function (Blueprint $table) {
            $table->dropIndex(['account_id']);
            $table->dropColumn('account_id');
        });

        Schema::table('student_assessments', function (Blueprint $table) {
            $table->dropIndex(['account_id']);
            $table->dropColumn('account_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['account_id']);
            $table->dropColumn('account_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['account_id']);
            $table->dropColumn('account_id');
        });
    }
};
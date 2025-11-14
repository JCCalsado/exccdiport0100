<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // the student
            $table->string('reference')->nullable()->index();
            $table->string('payment_channel')->nullable(); // paymongo/gcash/paymaya
            $table->enum('type', ['charge','payment'])->default('charge'); // charge = bill, payment = paid amount
            $table->decimal('amount', 12, 2)->default(0);
            $table->enum('status', ['pending','paid','failed','cancelled'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->text('meta')->nullable(); // store raw gateway response (json)
            $table->timestamps();
            $table->unsignedBigInteger('fee_id')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};

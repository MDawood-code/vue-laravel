<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->string('checkout_id')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('type');
            $table->string('brand');
            $table->double('amount');
            $table->string('currency');
            $table->string('merchant_transaction_id')->nullable();
            $table->bigInteger('invoice_id')->nullable();
            $table->json('result')->nullable();
            $table->json('result_details')->nullable();
            $table->unsignedTinyInteger('test_mode')->default(BOOLEAN_FALSE);
            $table->unsignedTinyInteger('status')->default(PAYMENT_STATUS_UNPAID);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

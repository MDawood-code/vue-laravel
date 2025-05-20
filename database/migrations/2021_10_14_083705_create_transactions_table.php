<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('uid');
            $table->double('cash_collected')->default(0);
            $table->double('amount_charged'); // Including Tax
            $table->double('tax')->default(0);
            $table->tinyInteger('type')->default(TRANSACTION_TYPE_CASH);
            $table->double('tip')->default(0);
            $table->string('reference')->nullable();
            $table->string('buyer_company_name')->nullable();
            $table->string('buyer_company_vat')->nullable();
            $table->tinyInteger('is_refunded')->default(BOOLEAN_FALSE);
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('branch_id');
            $table->unsignedInteger('refunded_transaction_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

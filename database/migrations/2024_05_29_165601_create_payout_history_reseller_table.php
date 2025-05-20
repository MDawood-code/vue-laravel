<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_history_reseller', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('reseller_id');
            $table->string('account_number')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('amount')->nullable();
            $table->date('date')->nullable();
            $table->timestamps();

            $table->foreign('reseller_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_history_reseller');
    }
};

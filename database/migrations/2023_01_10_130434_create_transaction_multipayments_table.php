<?php

use App\Models\Transaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_multipayments', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Transaction::class);
            $table->tinyInteger('transaction_type')->default(TRANSACTION_TYPE_CASH); //cash, mada, stc, card etc
            $table->double('amount');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_multipayments');
    }
};

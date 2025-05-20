<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_user_balance_deductions', function (Blueprint $table): void {
            $table->id();
            $table->double('amount');
            $table->unsignedTinyInteger('deduction_type');
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('balance_id')->constrained('balances')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user_balance_deductions');
    }
};

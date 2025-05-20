<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_bank_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('reseller_id');
            $table->string('account_title')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('branch_code')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->tinyInteger('status')->default(BOOLEAN_TRUE);
            $table->timestamps();

            $table->foreign('reseller_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_bank_details');
    }
};

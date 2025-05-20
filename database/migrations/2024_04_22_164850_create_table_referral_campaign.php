<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_campaign', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('referral_id');
            $table->string('referral_code')->nullable();
            $table->date('referral_commission')->nullable();
            $table->date('expiry_date')->nullable();
            $table->tinyInteger('status')->default(BOOLEAN_TRUE);
            $table->timestamps();

            $table->foreign('referral_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_campaign');
    }
};

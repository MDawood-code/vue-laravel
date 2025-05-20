<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_sms', function (Blueprint $table): void {
            $table->id();
            $table->string('number');
            $table->string('code');
            $table->tinyInteger('try')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_sms');
    }
};

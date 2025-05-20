<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_sms', function (Blueprint $table): void {
            // This column is used to store unique token for password reset
            $table->string('token')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('otp_sms', function (Blueprint $table): void {
            $table->dropColumn('token');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_campaign', function (Blueprint $table): void {
            $table->unique('referral_code')->change();
            $table->string('referral_commission')->change();
        });
    }

    public function down(): void {}
};

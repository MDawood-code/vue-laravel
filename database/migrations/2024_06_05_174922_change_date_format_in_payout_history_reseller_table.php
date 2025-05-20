<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payout_history_reseller', function (Blueprint $table): void {
            $table->dateTime('date')->change();
        });
    }

    public function down(): void
    {
        Schema::table('payout_history_reseller', function (Blueprint $table): void {
            $table->date('date')->change();
        });
    }
};

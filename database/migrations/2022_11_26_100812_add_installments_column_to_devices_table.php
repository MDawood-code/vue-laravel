<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            // initially in system, we had devices with fixed 1000 amount and 200 per installment
            // so total 5 installments. To account for old records, default 5 is necessary
            $table->unsignedInteger('installments')->default(5);
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->dropColumn('installments');
        });
    }
};

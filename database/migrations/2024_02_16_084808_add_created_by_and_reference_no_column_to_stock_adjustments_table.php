<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table): void {
            $table->unsignedInteger('created_by');
            $table->string('reference_no');
        });
    }

    public function down(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table): void {
            $table->dropColumn('created_by');
            $table->dropColumn('reference_no');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_adjustment_products', function (Blueprint $table): void {
            $table->dropColumn('note');
        });
    }

    public function down(): void
    {
        Schema::table('stock_adjustment_products', function (Blueprint $table): void {
            $table->string('note')->nullable();
        });
    }
};

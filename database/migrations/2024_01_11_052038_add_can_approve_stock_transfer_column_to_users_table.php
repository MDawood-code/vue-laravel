<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            //  staff can request/approve  stock transfer
            $table->boolean('can_approve_stock_transfer')->default(BOOLEAN_FALSE);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('can_approve_stock_transfer');
        });
    }
};

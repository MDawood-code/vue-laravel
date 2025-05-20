<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('can_add_product')->default(false);
            $table->boolean('can_edit_product')->default(false);
            $table->boolean('can_refund_transaction')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('can_add_product');
            $table->dropColumn('can_edit_product');
            $table->dropColumn('can_refund_transaction');
        });
    }
};

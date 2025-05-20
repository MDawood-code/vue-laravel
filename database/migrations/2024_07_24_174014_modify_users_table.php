<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['can_add_product', 'can_edit_product']);
            $table->tinyInteger('can_add_edit_product')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->tinyInteger('can_add_product')->default(0);
            $table->tinyInteger('can_edit_product')->default(0);
            $table->dropColumn('can_add_edit_product');
        });
    }
};

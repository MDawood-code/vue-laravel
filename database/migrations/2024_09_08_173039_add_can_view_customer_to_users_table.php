<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->tinyInteger('can_view_customer')->default(1)->after('can_add_edit_product');
            $table->tinyInteger('can_add_edit_customer')->default(1)->after('can_view_customer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('can_view_customer');
            $table->dropColumn('can_add_edit_customer');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_units', function (Blueprint $table): void {
            $table->string('odoo_reference_id')->nullable();
        });

        Schema::table('product_categories', function (Blueprint $table): void {
            $table->string('odoo_reference_id')->nullable();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('odoo_reference_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_units', function (Blueprint $table): void {
            $table->dropColumn('odoo_reference_id');
        });

        Schema::table('product_categories', function (Blueprint $table): void {
            $table->dropColumn('odoo_reference_id');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('odoo_reference_id');
        });
    }
};

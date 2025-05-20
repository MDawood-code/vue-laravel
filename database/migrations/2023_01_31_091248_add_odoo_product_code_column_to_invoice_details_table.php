<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_details', function (Blueprint $table): void {
            $table->string('odoo_product_code')->default(null)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_details', function (Blueprint $table): void {
            $table->dropColumn('odoo_product_code');
        });
    }
};

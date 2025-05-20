<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table): void {
            $table->string('name')->nullable()->change();
            $table->string('name_ar')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table): void {
            $table->string('name')->nullable(false)->change();
            $table->string('name_ar')->nullable()->change();
        });
    }
};

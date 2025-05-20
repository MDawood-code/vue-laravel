<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('cr')->nullable()->unique()->change();
            $table->string('vat')->nullable()->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropUnique('companies_cr_unique');
            $table->dropUnique('companies_vat_unique');
        });
    }
};

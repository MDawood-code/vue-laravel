<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('cr_verification')->nullable()->after('cr_certificate');
            $table->string('vat_verification')->nullable()->after('vat_certificate');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('cr_verification');
            $table->dropColumn('vat_verification');
        });
    }
};

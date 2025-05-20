<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('referral_code')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            //
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_user_licenses', function (Blueprint $table): void {
            $table->double('amount')->change();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_user_licenses', function (Blueprint $table): void {
            $table->unsignedInteger('amount')->change();
        });
    }
};

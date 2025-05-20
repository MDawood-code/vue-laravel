<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->unsignedBigInteger('billing_city')->nullable()->change();
            $table->unsignedBigInteger('billing_state')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('billing_city')->nullable()->change();
            $table->string('billing_state')->nullable()->change();
        });
    }
};

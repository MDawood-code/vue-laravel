<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->integer('status')->nullable()->after('email'); // Add 'after' to place the column after a specific column if needed
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            //
        });
    }
};

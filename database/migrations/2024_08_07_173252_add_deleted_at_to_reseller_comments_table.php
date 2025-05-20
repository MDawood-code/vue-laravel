<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reseller_comments', function (Blueprint $table): void {
            $table->softDeletes(); // Adds a deleted_at column for soft deletes
        });
    }

    public function down(): void
    {
        Schema::table('reseller_comments', function (Blueprint $table): void {
            //
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_tables', function (Blueprint $table): void {
            $table->boolean('is_drive_thru')->default(BOOLEAN_FALSE);
        });
    }

    public function down(): void
    {
        Schema::table('dining_tables', function (Blueprint $table): void {
            $table->dropColumn('is_drive_thru');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->unsignedBigInteger('dining_table_id')->nullable();
            $table->foreign('dining_table_id')->references('id')->on('dining_tables');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropForeign(['dining_table_id']);
            $table->dropColumn('dining_table_id');
        });
    }
};

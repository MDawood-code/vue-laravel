<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reseller_comments', function (Blueprint $table): void {
            $table->unsignedBigInteger('created_by')->after('reseller_id');

            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('reseller_comments', function (Blueprint $table): void {
            //
        });
    }
};

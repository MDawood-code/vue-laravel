<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_crash_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('screen')->nullable();
            $table->string('task')->nullable();
            $table->string('function')->nullable();
            $table->json('error')->nullable();
            $table->bigInteger('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_crash_logs');
    }
};

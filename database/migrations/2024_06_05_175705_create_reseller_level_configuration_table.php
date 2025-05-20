<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_level_configuration', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('reseller_id');
            $table->string('basic_commission')->nullable();
            $table->string('basic_retain_rate')->nullable();
            $table->string('basic_target')->nullable();
            $table->string('pro_commission')->nullable();
            $table->string('pro_retain_rate')->nullable();
            $table->string('pro_target')->nullable();
            $table->timestamps();

            $table->foreign('reseller_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_level_configuration');
    }
};

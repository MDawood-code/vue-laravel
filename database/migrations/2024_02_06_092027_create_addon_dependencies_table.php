<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_dependencies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('addon_id')->comment('This is the addon which depends on the dependent_addon_id');
            $table->unsignedBigInteger('dependent_addon_id');
            $table->timestamps();

            $table->foreign('addon_id')->references('id')->on('addons')->onDelete('cascade');
            $table->foreign('dependent_addon_id')->references('id')->on('addons')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_dependencies');
    }
};

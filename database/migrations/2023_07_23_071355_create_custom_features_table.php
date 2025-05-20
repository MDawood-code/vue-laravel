<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The name custom_features is because we may need Pennant package in future which uses features table
        Schema::create('custom_features', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->boolean('status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_features');
    }
};

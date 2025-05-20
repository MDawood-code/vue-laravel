<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This table will hold source of information from which a customer learns about anypos
        Schema::create('learning_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('source');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_sources');
    }
};

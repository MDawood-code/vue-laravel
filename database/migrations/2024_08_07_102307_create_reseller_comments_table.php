<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_comments', function (Blueprint $table): void {
            $table->id();
            $table->text('description');
            $table->unsignedBigInteger('reseller_id');
            $table->timestamps();

            $table->foreign('reseller_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_comments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_addons', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('addon_id');
            $table->decimal('price', 8, 2);
            $table->decimal('discount', 8, 2);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->tinyInteger('status')->default(BOOLEAN_FALSE);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('addon_id')->references('id')->on('addons')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_addons');
    }
};

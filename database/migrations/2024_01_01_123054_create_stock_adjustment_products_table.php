<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustment_products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('stock_adjustment_id');
            $table->unsignedInteger('product_id');
            $table->integer('quantity')->default(0);
            $table->string('note')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_products');
    }
};

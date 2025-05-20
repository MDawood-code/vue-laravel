<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfer_products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('stock_transfer_id');
            $table->unsignedInteger('product_id');
            $table->integer('requested_quantity')->default(0);
            $table->integer('approved_quantity')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_products');
    }
};

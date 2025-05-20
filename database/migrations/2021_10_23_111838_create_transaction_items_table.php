<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('name_en');
            $table->double('price');
            $table->unsignedInteger('quantity');
            $table->double('tax');
            $table->double('subtotal');
            $table->string('category');
            $table->string('unit');
            $table->string('barcode')->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
    }
};

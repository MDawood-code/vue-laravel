<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('name_en');
            $table->double('price');
            $table->string('barcode')->nullable();
            $table->string('image')->nullable();
            $table->tinyInteger('is_taxable')->default(BOOLEAN_TRUE);
            $table->unsignedInteger('product_category_id');
            $table->unsignedInteger('product_unit_id');
            $table->unsignedInteger('company_id');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('branch_id');
            $table->integer('quantity')->default(0);
            $table->boolean('status')->comment('1:Active, 0:Inactive')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};

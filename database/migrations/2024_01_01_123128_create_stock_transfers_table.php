<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('branch_from_id');
            $table->unsignedInteger('branch_to_id');
            $table->date('date')->comment('(Date) when Stock is Transfer');
            $table->unsignedInteger('product_id');
            $table->string('reference_no')->nullable();
            $table->integer('quantity')->default(0);
            $table->tinyInteger('status')->comment('1:Complete, 0:Pending')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};

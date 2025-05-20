<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_details', function (Blueprint $table): void {
            $table->id();
            $table->string('item');
            $table->unsignedInteger('quantity')->default(1);
            $table->double('amount')->default(0);
            $table->unsignedTinyInteger('type')->default(INVOICE_DETAIL_TYPE_SUBSCRIPTION);
            $table->unsignedInteger('invoice_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_details');
    }
};

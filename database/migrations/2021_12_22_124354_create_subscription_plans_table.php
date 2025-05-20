<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->tinyInteger('type');
            $table->tinyInteger('period');
            $table->float('price')->unsigned();
            $table->float('discount')->unsigned()->default(0);
            $table->float('user_price')->unsigned()->default(0);
            $table->float('user_discount')->unsigned()->default(0);
            $table->unsignedInteger('validity_days');
            $table->tinyInteger('is_trial')->default(BOOLEAN_FALSE);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};

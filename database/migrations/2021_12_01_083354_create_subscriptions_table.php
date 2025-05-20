<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->tinyInteger('type');
            $table->tinyInteger('period');
            $table->float('amount')->unsigned();
            $table->float('license_amount')->unsigned();
            $table->float('license_discount')->unsigned();
            $table->unsignedInteger('validity_days');
            $table->tinyInteger('is_trial')->default(BOOLEAN_FALSE);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('company_id');
            $table->tinyInteger('status')->default(BOOLEAN_FALSE);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

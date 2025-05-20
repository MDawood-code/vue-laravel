<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_user_licenses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('amount');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('subscription_id');
            $table->tinyInteger('status')->default(BOOLEAN_FALSE);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_user_licenses');
    }
};

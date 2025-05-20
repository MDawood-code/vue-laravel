<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('uid')->nullable();
            $table->double('amount_charged')->unsigned()->default(0);
            $table->unsignedTinyInteger('status')->default(INVOICE_STATUS_UNPAID);
            $table->unsignedTinyInteger('type')->default(INVOICE_TYPE_SUBSCRIPTION);
            $table->unsignedInteger('subscription_id');
            $table->unsignedInteger('company_id');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

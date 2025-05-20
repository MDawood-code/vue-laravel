<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('business_type')->nullable();
            $table->string('cr')->nullable();
            $table->string('cr_certificate')->nullable();
            $table->string('vat')->nullable();
            $table->string('vat_certificate')->nullable();
            $table->string('logo')->nullable();
            $table->string('code')->nullable();
            $table->json('addons')->nullable();
            $table->string('billing_address')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_state')->nullable();
            $table->string('billing_country')->nullable();
            $table->string('billing_post_code')->nullable();
            $table->boolean('round_off')->default(BOOLEAN_TRUE);
            $table->unsignedTinyInteger('status')->default(COMPANY_STATUS_KYC);
            $table->boolean('is_active')->default(BOOLEAN_FALSE);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->json('app_config')->nullable();
            $table->string('device_token')->nullable();
            $table->tinyInteger('type')->default(USER_TYPE_BUSINESS_OWNER);
            $table->boolean('is_active')->default(BOOLEAN_TRUE);
            $table->unsignedInteger('branch_id')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

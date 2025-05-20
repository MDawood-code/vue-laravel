<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(User::class, 'created_by')->nullable();
            $table->foreignIdFor(Company::class);
            $table->string('action'); //e.g. added a device
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_logs');
    }
};

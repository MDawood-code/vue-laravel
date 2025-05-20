<?php

use App\Models\Company;
use App\Models\ExternalIntegrationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_integrations', function (Blueprint $table): void {
            $table->id();
            $table->string('url');
            $table->string('secret_key');
            $table->foreignIdFor(Company::class);
            $table->foreignIdFor(ExternalIntegrationType::class);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_integrations');
    }
};

<?php

use App\Models\Company;
use App\Models\LearningSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questionnaires', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(LearningSource::class);
            $table->string('other_learning_source')->nullable();
            $table->string('preferred_platform');
            $table->string('new_or_existing');
            $table->foreignIdFor(Company::class);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaires');
    }
};

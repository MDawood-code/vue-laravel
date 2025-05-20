<?php

use App\Models\ActivityType;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->foreignIdFor(ActivityType::class);
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->dateTime('reminder')->nullable();
            $table->foreignIdFor(Company::class);
            $table->foreignIdFor(User::class, 'created_by');
            $table->foreignIdFor(User::class, 'assigned_to');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};

<?php

use App\Models\Activity;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->text('description');
            $table->foreignIdFor(Activity::class)->nullable();
            $table->foreignIdFor(Note::class)->nullable();
            $table->foreignIdFor(User::class, 'created_by');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};

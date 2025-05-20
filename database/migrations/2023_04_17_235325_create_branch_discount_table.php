<?php

use App\Models\Branch;
use App\Models\Discount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_discount', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Discount::class);
            $table->foreignIdFor(Branch::class);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_discount');
    }
};

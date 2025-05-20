<?php

use App\Models\Device;
use App\Models\Invoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Device::class);
            $table->foreignIdFor(Invoice::class);
            $table->double('amount')->unsigned()->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices_invoices');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_addons', function (Blueprint $table): void {
            $table->unsignedTinyInteger('trial_validity_days')->default(0);
            $table->date('trial_started_at')->nullable();
            $table->date('trial_ended_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('company_addons', function (Blueprint $table): void {
            $table->dropColumn('trial_validity_days');
            $table->dropColumn('trial_started_at');
            $table->dropColumn('trial_ended_at');
        });
    }
};

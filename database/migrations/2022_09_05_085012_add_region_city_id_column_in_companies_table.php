<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->foreignIdFor(City::class)->nullable();
            $table->foreignIdFor(Region::class)->nullable();
            $table->foreignIdFor(Country::class)->default(COUNTRY_SAUDI_ARABIA);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('city_Id');
            $table->dropColumn('region_id');
            $table->dropColumn('country_id');
        });
    }
};

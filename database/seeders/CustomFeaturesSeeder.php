<?php

namespace Database\Seeders;

use App\Models\CustomFeature;
use Illuminate\Database\Seeder;

class CustomFeaturesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CustomFeature::firstOrCreate(['title' => 'External Integration', 'status' => false]);
        CustomFeature::firstOrCreate(['title' => 'Odoo Integration', 'status' => false]);
        CustomFeature::firstOrCreate(['title' => 'Xero Integration', 'status' => false]);
        CustomFeature::firstOrCreate(['title' => 'Zoho Integration', 'status' => false]);
    }
}

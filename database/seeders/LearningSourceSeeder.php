<?php

namespace Database\Seeders;

use App\Models\LearningSource;
use Illuminate\Database\Seeder;

class LearningSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        LearningSource::firstOrCreate(['source' => 'Google Search']);
        LearningSource::firstOrCreate(['source' => 'Google Banner']);
        LearningSource::firstOrCreate(['source' => 'YouTube Ads']);
        LearningSource::firstOrCreate(['source' => 'Instagram']);
        LearningSource::firstOrCreate(['source' => 'WhatsApp']);
        LearningSource::firstOrCreate(['source' => 'Facebook']);
        LearningSource::firstOrCreate(['source' => 'TikTok']);
        LearningSource::firstOrCreate(['source' => 'Snapchat']);
        LearningSource::firstOrCreate(['source' => 'Twitter']);
        LearningSource::firstOrCreate(['source' => 'LinkedIn']);
        LearningSource::firstOrCreate(['source' => 'Any other platform']);
    }
}

<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class SourceSeeder extends Seeder
{
    public function run(): void
    {
        Source::firstOrCreate(
            ['key' => 'thairath_society'],
            [
                'name' => 'ThaiRath Society',
                'base_url' => 'https://www.thairath.co.th',
                'listing_url' => 'https://www.thairath.co.th/news/society',
                'adapter' => 'thairath',
                'is_active' => true,
                'config' => [],
            ],
        );
    }
}

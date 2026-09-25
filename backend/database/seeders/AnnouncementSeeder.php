<?php

namespace Database\Seeders;

use App\Models\Announcement;
use Illuminate\Database\Seeder;

class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        Announcement::firstOrCreate(
            ['title' => 'Alabang Booth'],
            [
                'label' => 'NEXT FIELD',
                'description' => 'SEPTEMBER 26–27 | 11AM–9PM | G STUDIOS, ALABANG',
                'event_date' => now()->addDays(7)->toDateString(),
                'event_time' => '11AM–9PM',
                'location' => 'G Studios, Alabang',
                'status' => Announcement::STATUS_PUBLISHED,
                'priority' => 0,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(30),
            ]
        );
    }
}

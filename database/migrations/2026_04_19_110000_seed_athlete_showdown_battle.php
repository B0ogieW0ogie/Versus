<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('battles')->updateOrInsert(
            ['slug' => 'athlete-showdown'],
            [
                'title' => "Who's the Better Athlete?",
                'description' => 'Max Carter, the established champion, goes head to head with rising star Jake Lewis.',
                'side_a_label' => 'Max Carter',
                'side_b_label' => 'Jake Lewis',
                'side_a_subtitle' => 'The Champion',
                'side_b_subtitle' => 'The Underdog',
                'side_a_image' => '/images/battles/figure-red.svg',
                'side_b_image' => '/images/battles/figure-blue.svg',
                'status' => 'active',
                'opens_at' => $now->copy()->subHour(),
                'closes_at' => $now->copy()->addHours(2)->addMinutes(15)->addSeconds(23),
                'total_pool' => 23450,
                'created_by_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('battles')->where('slug', 'athlete-showdown')->delete();
    }
};

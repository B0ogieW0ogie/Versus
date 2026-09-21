<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('battles:settle-due')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('challenges:close-due')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('challenges:score-feed')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('challenges:cleanup-uploads')
    ->daily();

// News Feed "Top change" checkpoints (versus.challenges.top_checkpoint_hours, default every 8 h).
Schedule::command('challenges:track-top')
    ->cron('0 */'.max(1, (int) config('versus.challenges.top_checkpoint_hours')).' * * *')
    ->withoutOverlapping();

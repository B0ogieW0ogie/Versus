<?php

namespace App\Console\Commands;

use App\Models\Challenge;
use App\Services\Challenges\TrackTopResponses;
use Illuminate\Console\Command;
use Throwable;

class TrackTopResponsesCommand extends Command
{
    protected $signature = 'challenges:track-top';

    protected $description = 'Checkpoint the Top-10 responses of active challenges and post News Feed events for moves.';

    public function handle(TrackTopResponses $tracker): int
    {
        Challenge::query()
            ->where('status', Challenge::STATUS_ACTIVE)
            ->eachById(function (Challenge $challenge) use ($tracker): void {
                try {
                    $tracker->checkpoint($challenge);
                } catch (Throwable $e) {
                    report($e);
                    $this->error("Failed to track challenge #{$challenge->id}: {$e->getMessage()}");
                }
            });

        return self::SUCCESS;
    }
}

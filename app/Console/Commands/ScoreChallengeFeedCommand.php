<?php

namespace App\Console\Commands;

use App\Services\Challenges\FeedScorer;
use Illuminate\Console\Command;

class ScoreChallengeFeedCommand extends Command
{
    protected $signature = 'challenges:score-feed';

    protected $description = 'Recompute feed_score for active challenges.';

    public function handle(FeedScorer $scorer): int
    {
        $this->info('Scored '.$scorer->recalculate(now()).' challenges.');

        return self::SUCCESS;
    }
}

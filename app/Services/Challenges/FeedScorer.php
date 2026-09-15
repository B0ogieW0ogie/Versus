<?php

namespace App\Services\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeVote;
use Carbon\CarbonInterface;

class FeedScorer
{
    public function score(Challenge $challenge, int $recentActivity, CarbonInterface $now): float
    {
        /** @var array{freshness_half_life_hours: int|float, weight_freshness: float, weight_activity: float, ending_soon_hours: int|float, weight_ending_soon: float} $cfg */
        $cfg = config('versus.challenges.feed');

        $ageHours = abs(($challenge->created_at ?? $now)->diffInMinutes($now)) / 60;
        $freshness = 0.5 ** ($ageHours / $cfg['freshness_half_life_hours']);

        $activity = log1p(max(0, $recentActivity));

        $endingSoon = 0.0;
        if ($challenge->ends_at !== null) {
            $hoursLeft = $now->diffInMinutes($challenge->ends_at) / 60;
            $endingSoon = ($hoursLeft >= 0 && $hoursLeft <= $cfg['ending_soon_hours']) ? 1.0 : 0.0;
        }

        return round(
            $cfg['weight_freshness'] * $freshness
            + $cfg['weight_activity'] * $activity
            + $cfg['weight_ending_soon'] * $endingSoon,
            6,
        );
    }

    public function recalculate(CarbonInterface $now): int
    {
        $challenges = Challenge::where('status', Challenge::STATUS_ACTIVE)->get();
        if ($challenges->isEmpty()) {
            return 0;
        }

        $ids = $challenges->modelKeys();
        $since = $now->copy()->subHours((int) config('versus.challenges.feed.activity_window_hours'));

        $responses = ChallengeEntry::query()
            ->whereIn('challenge_id', $ids)
            ->where('is_original', false)
            ->where('status', ChallengeEntry::STATUS_READY)
            ->where('created_at', '>=', $since)
            ->selectRaw('challenge_id, COUNT(*) as aggregate')
            ->groupBy('challenge_id')
            ->pluck('aggregate', 'challenge_id');

        $votes = ChallengeVote::query()
            ->whereIn('challenge_id', $ids)
            ->where('updated_at', '>=', $since)
            ->selectRaw('challenge_id, COUNT(*) as aggregate')
            ->groupBy('challenge_id')
            ->pluck('aggregate', 'challenge_id');

        foreach ($challenges as $challenge) {
            $activity = (int) ($responses[$challenge->id] ?? 0) + (int) ($votes[$challenge->id] ?? 0);
            $challenge->feed_score = $this->score($challenge, $activity, $now);
            $challenge->saveQuietly();
        }

        return $challenges->count();
    }
}

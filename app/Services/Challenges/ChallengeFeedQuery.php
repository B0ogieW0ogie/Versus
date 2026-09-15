<?php

namespace App\Services\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeView;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ChallengeFeedQuery
{
    /**
     * @param  array<int, int>  $excludeIds
     * @param  array<int, int>  $guestViewedIds
     * @return Collection<int, Challenge>
     */
    public function next(?User $viewer, array $excludeIds, int $limit, array $guestViewedIds = []): Collection
    {
        $viewedIds = $viewer !== null
            ? ChallengeView::where('user_id', $viewer->id)->pluck('challenge_id')->all()
            : $guestViewedIds;
        $viewedIds = array_values(array_unique(array_map('intval', $viewedIds)));

        $query = Challenge::query()
            ->where('status', Challenge::STATUS_ACTIVE)
            ->where('ends_at', '>', now())
            ->whereNotIn('id', array_map('intval', $excludeIds));

        if ($viewedIds !== []) {
            // Integers only (cast above), so inlining is injection-safe and portable across SQLite/Postgres.
            $query->orderByRaw('CASE WHEN id IN ('.implode(',', $viewedIds).') THEN 1 ELSE 0 END');
        }

        return $query
            ->orderByDesc('feed_score')
            ->orderByDesc('id')
            ->limit($limit)
            ->with(['user', 'original'])
            ->get();
    }
}

<?php

namespace App\Services\Recommendations;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/** Left desktop column: popular creators grouped by challenge category. MVP recommends profiles only. */
class RecommendedProfiles
{
    private const GROUPS = 4;

    private const PER_GROUP = 3;

    private const CACHE_SECONDS = 600;

    /** @return list<array{category: string, users: list<User>}> */
    public function groups(?User $viewer): array
    {
        /** @var array<string, list<int>> $ids */
        $ids = Cache::remember('recommended-profiles:v1', self::CACHE_SECONDS, fn (): array => $this->rankedIds());

        $users = User::query()
            ->whereIn('id', array_unique(array_merge([], ...array_values($ids))))
            ->get()
            ->keyBy('id');

        $groups = [];
        foreach ($ids as $category => $userIds) {
            $picked = [];
            foreach ($userIds as $id) {
                $user = $users->get($id);
                if ($user !== null && $user->id !== $viewer?->id) {
                    $picked[] = $user;
                }
            }
            $picked = array_slice($picked, 0, self::PER_GROUP);
            if ($picked !== []) {
                $groups[] = ['category' => $category, 'users' => $picked];
            }
            if (count($groups) === self::GROUPS) {
                break;
            }
        }

        return $groups;
    }

    /**
     * Per category, authors ranked by votes + likes on their ready videos. One extra per group so
     * the viewer can be dropped without leaving a gap.
     *
     * @return array<string, list<int>>
     */
    private function rankedIds(): array
    {
        $rows = ChallengeEntry::query()
            ->join('challenges', 'challenges.id', '=', 'challenge_entries.challenge_id')
            ->whereIn('challenges.status', [Challenge::STATUS_ACTIVE, Challenge::STATUS_CLOSED])
            ->whereNotNull('challenges.category')
            ->where('challenge_entries.status', ChallengeEntry::STATUS_READY)
            ->groupBy('challenges.category', 'challenge_entries.user_id')
            ->selectRaw('challenges.category as category, challenge_entries.user_id as user_id, SUM(challenge_entries.votes_count + challenge_entries.likes_count) as score')
            ->orderByDesc('score')
            ->get();

        $ranked = [];
        foreach (Challenge::CATEGORIES as $category) {
            $ids = $rows->where('category', $category)->pluck('user_id')->map(fn ($id): int => (int) $id)->take(self::PER_GROUP + 1)->values()->all();
            if ($ids !== []) {
                $ranked[$category] = $ids;
            }
        }

        return $ranked;
    }
}

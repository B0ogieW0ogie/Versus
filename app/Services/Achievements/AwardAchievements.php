<?php

namespace App\Services\Achievements;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeVote;
use App\Models\FeedPost;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

/**
 * Posts an achievement to the News Feed when a user reaches a milestone (config versus.achievements).
 * Each milestone is awarded once: the feed_posts (type, user_id, key) unique index is the guard.
 */
class AwardAchievements
{
    public const KIND_VOTES = 'votes';

    public const KIND_RESPONSES = 'responses';

    public const KIND_CHALLENGES = 'challenges';

    public const KIND_WINS = 'wins';

    /** Never throws: an achievement must not break the action that earned it. */
    public function check(User $user, string $kind): void
    {
        try {
            $milestones = (array) config('versus.achievements.'.$kind, []);
            if ($milestones === []) {
                return;
            }

            $count = $this->count($user, $kind);
            foreach ($milestones as $milestone) {
                if ($count >= (int) $milestone) {
                    $this->award($user, $kind.'_'.$milestone);
                }
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function count(User $user, string $kind): int
    {
        return match ($kind) {
            self::KIND_VOTES => ChallengeVote::where('user_id', $user->id)->count(),
            self::KIND_RESPONSES => ChallengeEntry::where('user_id', $user->id)
                ->where('is_original', false)->where('status', ChallengeEntry::STATUS_READY)->count(),
            self::KIND_CHALLENGES => Challenge::where('user_id', $user->id)
                ->whereIn('status', [Challenge::STATUS_ACTIVE, Challenge::STATUS_CLOSED])->count(),
            self::KIND_WINS => Challenge::query()
                ->where('status', Challenge::STATUS_CLOSED)
                ->whereHas('winnerEntry', fn ($q) => $q->where('user_id', $user->id))
                ->count(),
            default => 0,
        };
    }

    private function award(User $user, string $key): void
    {
        $exists = FeedPost::where('type', FeedPost::TYPE_ACHIEVEMENT)->where('user_id', $user->id)->where('key', $key)->exists();
        if ($exists) {
            return;
        }

        try {
            FeedPost::create([
                'type' => FeedPost::TYPE_ACHIEVEMENT,
                'user_id' => $user->id,
                'key' => $key,
                'published_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Awarded concurrently by another request.
        }
    }
}

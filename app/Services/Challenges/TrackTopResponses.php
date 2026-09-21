<?php

namespace App\Services\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\FeedPost;
use Illuminate\Support\Facades\DB;

/**
 * Checkpoint of the viewing-feed Top-N of responses (config versus.challenges.top_size).
 *
 * The Top-N ranks responses by likes — how they perform in the video feed — and is deliberately
 * separate from votes, which only decide the final Top-3. A move inside the Top-N between two
 * checkpoints posts a News Feed event; unchanged positions and anything below the Top-N post nothing.
 */
class TrackTopResponses
{
    /** @return int the number of Top-change events posted */
    public function checkpoint(Challenge $challenge): int
    {
        $size = (int) config('versus.challenges.top_size');

        return DB::transaction(function () use ($challenge, $size): int {
            /** @var Challenge $challenge */
            $challenge = Challenge::whereKey($challenge->id)->lockForUpdate()->firstOrFail();
            $firstCheckpoint = $challenge->top_checked_at === null;

            $ranked = ChallengeEntry::query()
                ->where('challenge_id', $challenge->id)
                ->where('is_original', false)
                ->where('status', ChallengeEntry::STATUS_READY)
                ->orderByDesc('likes_count')
                ->orderBy('submitted_at')
                ->orderBy('id')
                ->limit($size)
                ->get(['id', 'user_id', 'top_rank']);

            $posted = 0;
            $inTop = [];
            foreach ($ranked->values() as $index => $entry) {
                $rank = $index + 1;
                $inTop[] = $entry->id;
                $previous = $entry->top_rank;

                // The very first checkpoint only records positions: everything would look like a move.
                if (! $firstCheckpoint && $previous !== $rank) {
                    FeedPost::create([
                        'type' => FeedPost::TYPE_TOP_CHANGE,
                        'user_id' => $entry->user_id,
                        'challenge_id' => $challenge->id,
                        'entry_id' => $entry->id,
                        'data' => ['from' => $previous, 'to' => $rank],
                        'published_at' => now(),
                    ]);
                    $posted++;
                }

                if ($previous !== $rank) {
                    ChallengeEntry::whereKey($entry->id)->update(['top_rank' => $rank]);
                }
            }

            ChallengeEntry::query()
                ->where('challenge_id', $challenge->id)
                ->whereNotNull('top_rank')
                ->whereNotIn('id', $inTop)
                ->update(['top_rank' => null]);

            $challenge->forceFill(['top_checked_at' => now()])->save();

            return $posted;
        });
    }
}

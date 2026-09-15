<?php

namespace App\Actions\Challenges;

use App\Events\ChallengeClosed;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeVote;
use App\Notifications\ChallengeResults;
use Illuminate\Support\Facades\DB;
use Throwable;

class CloseChallengeAction
{
    public function __invoke(Challenge $challenge): Challenge
    {
        $closed = DB::transaction(function () use ($challenge): ?Challenge {
            /** @var Challenge $challenge */
            $challenge = Challenge::whereKey($challenge->id)->lockForUpdate()->firstOrFail();

            if ($challenge->status !== Challenge::STATUS_ACTIVE || $challenge->ends_at === null || now()->lt($challenge->ends_at)) {
                return null;
            }

            /** @var array<int, int> $counts */
            $counts = ChallengeVote::query()
                ->where('challenge_id', $challenge->id)
                ->selectRaw('entry_id, COUNT(*) as aggregate')
                ->groupBy('entry_id')
                ->pluck('aggregate', 'entry_id')
                ->map(fn ($v): int => (int) $v)
                ->all();

            $entries = ChallengeEntry::query()
                ->where('challenge_id', $challenge->id)
                ->where('status', ChallengeEntry::STATUS_READY)
                ->orderBy('submitted_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $winner = null;
            $best = 0;
            foreach ($entries as $entry) {
                $votes = $counts[$entry->id] ?? 0;
                if ($entry->votes_count !== $votes) {
                    $entry->votes_count = $votes;
                    $entry->save();
                }
                // Strictly greater: on a tie the earlier submission keeps the lead.
                if ($votes > $best) {
                    $best = $votes;
                    $winner = $entry;
                }
            }

            $challenge->fill([
                'status' => Challenge::STATUS_CLOSED,
                'winner_entry_id' => $winner?->id,
                'closed_at' => now(),
                'votes_count' => array_sum($counts),
                'feed_score' => 0,
            ])->save();

            return $challenge;
        });

        if ($closed === null) {
            return $challenge->fresh() ?? $challenge;
        }

        ChallengeClosed::dispatch($closed);
        $this->notifyResults($closed);

        return $closed;
    }

    private function notifyResults(Challenge $challenge): void
    {
        $winner = $challenge->winnerEntry()->with('user')->first();

        $entries = ChallengeEntry::query()
            ->where('challenge_id', $challenge->id)
            ->where('status', ChallengeEntry::STATUS_READY)
            ->with('user')
            ->get()
            ->unique('user_id');

        foreach ($entries as $entry) {
            $result = match (true) {
                $winner === null => ChallengeResults::NO_VOTES,
                $winner->id === $entry->id => ChallengeResults::WON,
                default => ChallengeResults::OVER,
            };

            try {
                $entry->user->notify(new ChallengeResults($challenge, $result, $winner?->user?->name, $winner?->id));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}

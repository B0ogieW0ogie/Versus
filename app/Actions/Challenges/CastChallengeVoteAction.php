<?php

namespace App\Actions\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeVote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CastChallengeVoteAction
{
    public function __invoke(User $user, ChallengeEntry $entry): ChallengeVote
    {
        return DB::transaction(function () use ($user, $entry): ChallengeVote {
            /** @var Challenge $challenge */
            $challenge = Challenge::whereKey($entry->challenge_id)->lockForUpdate()->firstOrFail();

            if (! $challenge->isOpen()) {
                throw ValidationException::withMessages(['challenge' => __('challenges.not_open')]);
            }

            /** @var ChallengeEntry $entry */
            $entry = ChallengeEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if ($entry->status !== ChallengeEntry::STATUS_READY) {
                throw ValidationException::withMessages(['entry' => __('challenges.entry_not_votable')]);
            }
            if ($entry->user_id === $user->id) {
                throw ValidationException::withMessages(['entry' => __('challenges.cannot_vote_own')]);
            }

            /** @var ChallengeVote|null $vote */
            $vote = ChallengeVote::query()
                ->where('user_id', $user->id)
                ->where('challenge_id', $challenge->id)
                ->lockForUpdate()
                ->first();

            if ($vote === null) {
                $vote = ChallengeVote::create([
                    'user_id' => $user->id,
                    'challenge_id' => $challenge->id,
                    'entry_id' => $entry->id,
                ]);
                $entry->increment('votes_count');
                $challenge->increment('votes_count');

                return $vote;
            }

            if ($vote->entry_id === $entry->id) {
                return $vote;
            }

            ChallengeEntry::whereKey($vote->entry_id)
                ->where('votes_count', '>', 0)
                ->lockForUpdate()
                ->decrement('votes_count');

            $vote->entry_id = $entry->id;
            $vote->save();
            $entry->increment('votes_count');

            return $vote;
        });
    }
}

<?php

namespace App\Services\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class MyChallengesQuery
{
    /** @return list<array{challenge: Challenge, state: string, has_entry: bool, my_entry_id: int|null, winner_name: string|null, winner_entry_id: int|null}> */
    public function cards(User $user, int $limit): array
    {
        $challenges = $this->base($user)
            ->with(['user:id,name,username', 'original', 'winnerEntry.user:id,name,username'])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'processing' THEN 1 WHEN 'failed' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE WHEN status = 'active' THEN ends_at END")
            ->orderByDesc('closed_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $entries = ChallengeEntry::query()
            ->where('user_id', $user->id)
            ->whereIn('challenge_id', $challenges->modelKeys())
            ->get()
            ->keyBy('challenge_id');

        return $challenges->map(function (Challenge $challenge) use ($user, $entries): array {
            /** @var ChallengeEntry|null $entry */
            $entry = $entries->get($challenge->id);
            $isOwn = $challenge->user_id === $user->id;

            $state = match (true) {
                $challenge->status === Challenge::STATUS_CLOSED => 'closed',
                $isOwn && $challenge->status === Challenge::STATUS_FAILED => 'own_failed',
                $isOwn && $challenge->status === Challenge::STATUS_PROCESSING => 'own_processing',
                $isOwn => 'own',
                $entry === null => $challenge->isOpen() ? 'accepted_open' : 'accepted_expired',
                $entry->status === ChallengeEntry::STATUS_PROCESSING => 'response_processing',
                default => 'response_ready',
            };

            return [
                'challenge' => $challenge,
                'state' => $state,
                'has_entry' => ! $isOwn && $entry !== null,
                'my_entry_id' => $entry?->id,
                'winner_name' => $this->winnerName($challenge),
                'winner_entry_id' => $challenge->winner_entry_id,
            ];
        })->values()->all();
    }

    public function activeCount(User $user): int
    {
        return $this->base($user)->where('status', Challenge::STATUS_ACTIVE)->count();
    }

    private function winnerName(Challenge $challenge): ?string
    {
        $user = $challenge->winnerEntry?->user;
        if ($user === null) {
            return null;
        }

        return $user->username !== null ? '@'.$user->username : $user->name;
    }

    /** @return Builder<Challenge> */
    private function base(User $user): Builder
    {
        return Challenge::query()->where(function (Builder $query) use ($user): void {
            $query->where('user_id', $user->id)
                ->orWhereIn('id', ChallengeParticipant::select('challenge_id')->where('user_id', $user->id));
        });
    }
}

<?php

namespace App\Actions\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeParticipant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AcceptChallengeAction
{
    public function __invoke(User $user, Challenge $challenge): ChallengeParticipant
    {
        if ($challenge->user_id === $user->id) {
            throw ValidationException::withMessages(['challenge' => __('challenges.own_challenge')]);
        }
        if (! $challenge->isOpen()) {
            throw ValidationException::withMessages(['challenge' => __('challenges.not_open')]);
        }

        return ChallengeParticipant::firstOrCreate(
            ['user_id' => $user->id, 'challenge_id' => $challenge->id],
            ['accepted_at' => now()],
        );
    }
}

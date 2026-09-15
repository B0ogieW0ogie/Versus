<?php

namespace App\Actions\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeParticipant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class LeaveChallengeAction
{
    public function __invoke(User $user, Challenge $challenge): void
    {
        if (ChallengeEntry::where('challenge_id', $challenge->id)->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages(['challenge' => __('challenges.cannot_leave_with_entry')]);
        }

        ChallengeParticipant::where('challenge_id', $challenge->id)->where('user_id', $user->id)->delete();
    }
}

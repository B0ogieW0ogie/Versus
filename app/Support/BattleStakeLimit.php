<?php

namespace App\Support;

use App\Models\Battle;
use App\Models\User;
use App\Models\Vote;

final class BattleStakeLimit
{
    public static function maxPerUser(): float
    {
        return (float) config('versus.max_battle_stake_per_user');
    }

    public static function userTotalInBattle(User|int $user, Battle|int $battle): float
    {
        $userId = $user instanceof User ? $user->id : $user;
        $battleId = $battle instanceof Battle ? $battle->id : $battle;

        return (float) Vote::query()
            ->where('user_id', $userId)
            ->where('battle_id', $battleId)
            ->sum('amount');
    }

    public static function remainingForUser(User|int $user, Battle|int $battle): float
    {
        $remaining = self::maxPerUser() - self::userTotalInBattle($user, $battle);

        return max(0, round($remaining, 2));
    }

    public static function maxSingleVoteForUser(User|int $user, Battle|int $battle): float
    {
        $perVoteCap = (float) config('versus.max_vote_amount');
        $remainingBattle = self::remainingForUser($user, $battle);

        return max(0, min($perVoteCap, $remainingBattle));
    }
}

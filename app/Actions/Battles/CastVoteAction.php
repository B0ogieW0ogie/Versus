<?php

namespace App\Actions\Battles;

use App\Models\Battle;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vote;
use App\Support\BattleStakeLimit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CastVoteAction
{
    public function __invoke(User $user, Battle $battle, string $side, float $amount): Vote
    {
        if (! in_array($side, [Battle::SIDE_A, Battle::SIDE_B], true)) {
            throw ValidationException::withMessages(['side' => __('battle.invalid_side')]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => __('battle.amount_too_small')]);
        }

        return DB::transaction(function () use ($user, $battle, $side, $amount) {
            /** @var Battle $battle */
            $battle = Battle::whereKey($battle->id)->lockForUpdate()->firstOrFail();

            if (! $battle->isOpenForVoting()) {
                throw ValidationException::withMessages(['battle' => __('battle.battle_not_open')]);
            }

            $wasLastShot = $battle->status === Battle::STATUS_LAST_SHOT;

            /** @var User $user */
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ((float) $user->balance < $amount) {
                throw ValidationException::withMessages(['amount' => __('battle.insufficient_balance')]);
            }

            $cap = (float) config('versus.max_vote_amount');
            if ($amount > $cap) {
                throw ValidationException::withMessages([
                    'amount' => __('battle.vote_cap_reached'),
                ]);
            }

            $stakedInBattle = BattleStakeLimit::userTotalInBattle($user, $battle);
            $battleCap = BattleStakeLimit::maxPerUser();
            if ($stakedInBattle + $amount > $battleCap) {
                $remaining = BattleStakeLimit::remainingForUser($user, $battle);
                throw ValidationException::withMessages([
                    'amount' => __('battle.battle_stake_cap_reached', [
                        'max' => number_format((int) $battleCap, 0, '.', ' '),
                        'remaining' => number_format((int) $remaining, 0, '.', ' '),
                    ]),
                ]);
            }

            $user->balance = (float) $user->balance - $amount;
            $user->save();

            $vote = Vote::create([
                'user_id' => $user->id,
                'battle_id' => $battle->id,
                'side' => $side,
                'amount' => $amount,
                'weight' => $amount,
                'referrer_id' => $user->referred_by_id,
            ]);

            $battle->total_pool = (float) $battle->total_pool + $amount;
            $battle->save();

            Transaction::create([
                'user_id' => $user->id,
                'type' => Transaction::TYPE_VOTE_STAKE,
                'amount' => -$amount,
                'balance_after' => $user->balance,
                'battle_id' => $battle->id,
                'meta' => ['side' => $side, 'vote_id' => $vote->id],
            ]);

            if ($wasLastShot) {
                app(SettleBattleAction::class)($battle);
            }

            return $vote;
        });
    }
}

<?php

namespace App\Actions\Battles;

use App\Models\Battle;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CastVoteAction
{
    public function __invoke(User $user, Battle $battle, string $side, float $amount): Vote
    {
        if (! in_array($side, [Battle::SIDE_A, Battle::SIDE_B], true)) {
            throw ValidationException::withMessages(['side' => 'Неверная сторона.']);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Сумма должна быть больше нуля.']);
        }

        return DB::transaction(function () use ($user, $battle, $side, $amount) {
            /** @var Battle $battle */
            $battle = Battle::whereKey($battle->id)->lockForUpdate()->firstOrFail();

            if (! $battle->isOpenForVoting()) {
                throw ValidationException::withMessages(['battle' => 'Голосование в баттле закрыто.']);
            }

            /** @var User $user */
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ((float) $user->balance < $amount) {
                throw ValidationException::withMessages(['amount' => 'Недостаточно средств на балансе.']);
            }

            $cap = (float) config('versus.vote_cap_per_user');
            $alreadyStaked = (float) Vote::where('user_id', $user->id)->sum('amount');
            if ($alreadyStaked + $amount > $cap) {
                $remaining = max(0.0, $cap - $alreadyStaked);
                throw ValidationException::withMessages([
                    'amount' => 'Превышен лимит голосов на пользователя ('.(int) $cap.'). Доступно: '.number_format($remaining, 2, '.', '').'.',
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

            return $vote;
        });
    }
}

<?php

namespace App\Actions\Battles;

use App\Models\Battle;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AddBattlePoolAction
{
    public function __invoke(Battle $battle, float $amount, ?string $note = null): Battle
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Сумма должна быть больше нуля.',
            ]);
        }

        $amount = round($amount, 2);

        return DB::transaction(function () use ($battle, $amount, $note): Battle {
            /** @var Battle $battle */
            $battle = Battle::whereKey($battle->id)->lockForUpdate()->firstOrFail();

            if ($battle->status === Battle::STATUS_SETTLED) {
                throw new RuntimeException('Нельзя пополнить пул завершённого баттла.');
            }

            $battle->total_pool = round((float) $battle->total_pool + $amount, 2);
            $battle->save();

            $meta = array_filter([
                'note' => $note !== null && $note !== '' ? $note : null,
            ]);

            Transaction::create([
                'user_id' => null,
                'type' => Transaction::TYPE_BATTLE_POOL_CREDIT,
                'amount' => $amount,
                'balance_after' => null,
                'battle_id' => $battle->id,
                'meta' => $meta !== [] ? $meta : null,
            ]);

            return $battle;
        });
    }
}

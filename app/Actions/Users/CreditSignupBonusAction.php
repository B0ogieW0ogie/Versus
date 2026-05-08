<?php

namespace App\Actions\Users;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreditSignupBonusAction
{
    public function __invoke(User $user): void
    {
        $bonus = round((float) config('versus.signup_bonus'), 2);
        if ($bonus <= 0) {
            return;
        }

        DB::transaction(function () use ($user, $bonus): void {
            $lockedUser = User::query()
                ->lockForUpdate()
                ->findOrFail($user->id);

            $alreadyCredited = Transaction::query()
                ->where('user_id', $lockedUser->id)
                ->where('type', Transaction::TYPE_SIGNUP_BONUS)
                ->exists();

            if ($alreadyCredited) {
                return;
            }

            $newBalance = round((float) $lockedUser->balance + $bonus, 2);
            $lockedUser->forceFill(['balance' => $newBalance])->save();

            Transaction::create([
                'user_id' => $lockedUser->id,
                'type' => Transaction::TYPE_SIGNUP_BONUS,
                'amount' => $bonus,
                'balance_after' => $newBalance,
            ]);
        });
    }
}

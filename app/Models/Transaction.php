<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'type', 'amount', 'balance_after', 'battle_id', 'meta'])]
class Transaction extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_SIGNUP_BONUS = 'signup_bonus';

    public const TYPE_VOTE_STAKE = 'vote_stake';

    public const TYPE_VOTE_PAYOUT = 'vote_payout';

    public const TYPE_REFERRAL_REWARD = 'referral_reward';

    public const TYPE_PROJECT_FEE = 'project_fee';

    public const TYPE_BURN = 'burn';

    public const TYPE_REWARD_POOL_CREDIT = 'reward_pool_credit';

    public const TYPE_REWARD_POOL_DEBIT = 'reward_pool_debit';

    public const TYPE_ADMIN_GRANT = 'admin_grant';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

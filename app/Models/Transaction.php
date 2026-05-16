<?php

namespace App\Models;

use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Lang;

#[Fillable(['user_id', 'type', 'amount', 'balance_after', 'battle_id', 'meta'])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

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

    public const TYPE_BATTLE_POOL_CREDIT = 'battle_pool_credit';

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

    public function userFacingLabel(): string
    {
        $key = 'transactions.types.'.$this->type;

        return Lang::has($key) ? __($key) : $this->type;
    }
}

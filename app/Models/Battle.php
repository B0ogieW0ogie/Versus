<?php

namespace App\Models;

use Database\Factories\BattleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $opens_at
 * @property Carbon|null $closes_at
 * @property Carbon|null $settled_at
 */
#[Fillable([
    'slug',
    'title',
    'description',
    'side_a_label',
    'side_b_label',
    'side_a_subtitle',
    'side_b_subtitle',
    'side_a_image',
    'side_b_image',
    'status',
    'opens_at',
    'closes_at',
    'winning_side',
    'total_pool',
    'created_by_id',
    'settled_at',
])]
class Battle extends Model
{
    /** @use HasFactory<BattleFactory> */
    use HasFactory;

    public const SIDE_A = 'A';

    public const SIDE_B = 'B';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_SETTLED = 'settled';

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'settled_at' => 'datetime',
            'total_pool' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<Vote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function isOpenForVoting(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->closes_at !== null
            && $this->closes_at->isFuture();
    }
}

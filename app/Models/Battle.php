<?php

namespace App\Models;

use Database\Factories\BattleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $opens_at
 * @property Carbon|null $closes_at
 * @property Carbon|null $settled_at
 * @property Carbon|null $ai_screened_at
 */
#[Fillable([
    'slug', 'title', 'description',
    'side_a_label', 'side_b_label', 'side_a_subtitle', 'side_b_subtitle',
    'side_a_image', 'side_b_image',
    'status', 'opens_at', 'closes_at', 'winning_side',
    'total_pool', 'created_by_id', 'settled_at', 'category_id',
    'is_sponsored', 'sponsor_handle', 'ai_screened_at',
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
            'ai_screened_at' => 'datetime',
            'total_pool' => 'decimal:2',
            'is_sponsored' => 'boolean',
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

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * @return Collection<int, self>
     */
    public static function sponsoredActive(): Collection
    {
        return self::query()
            ->active()
            ->where('is_sponsored', true)
            ->orderBy('closes_at')
            ->limit(10)
            ->get();
    }

    public function compactPool(): string
    {
        $n = (float) $this->total_pool;

        if ($n < 1000) {
            return (string) (int) $n;
        }

        $k = $n / 1000;

        if (fmod($k, 1.0) === 0.0) {
            return ((int) $k).'k';
        }

        return number_format($k, 1, '.', '').'k';
    }

    public function isOpenForVoting(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->closes_at !== null
            && $this->closes_at->isFuture()
            && ($this->opens_at === null || ! $this->opens_at->isFuture());
    }
}

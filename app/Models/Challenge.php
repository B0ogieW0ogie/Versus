<?php

namespace App\Models;

use Database\Factories\ChallengeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property Carbon|null $ends_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $reminder_sent_at
 * @property Carbon|null $top_checked_at
 */
#[Fillable([
    'slug', 'user_id', 'title', 'rules', 'category', 'format', 'opponent_id', 'duration', 'ends_at', 'status',
    'winner_entry_id', 'closed_at', 'reminder_sent_at', 'feed_score',
    'entries_count', 'votes_count', 'top_checked_at',
])]
class Challenge extends Model
{
    /** @use HasFactory<ChallengeFactory> */
    use HasFactory;

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_FAILED = 'failed';

    public const DURATION_3D = '3d';

    public const DURATION_7D = '7d';

    public const DURATION_14D = '14d';

    public const DURATION_30D = '30d';

    public const FORMAT_PUBLIC = 'public';

    /** 1v1: only the creator and the chosen opponent may respond; anyone can watch and vote. */
    public const FORMAT_DUEL = 'duel';

    public const FORMATS = [self::FORMAT_PUBLIC, self::FORMAT_DUEL];

    public const CATEGORY_SPORTS = 'sports';

    public const CATEGORY_MUSIC = 'music';

    public const CATEGORY_GAMING = 'gaming';

    public const CATEGORY_CREATIVITY = 'creativity';

    public const CATEGORY_SKILLS = 'skills';

    public const CATEGORY_LIFESTYLE = 'lifestyle';

    public const CATEGORY_OTHER = 'other';

    /** In display order. Labels live in lang/{locale}/challenges.php as category_{key}. */
    public const CATEGORIES = [
        self::CATEGORY_SPORTS,
        self::CATEGORY_MUSIC,
        self::CATEGORY_GAMING,
        self::CATEGORY_CREATIVITY,
        self::CATEGORY_SKILLS,
        self::CATEGORY_LIFESTYLE,
        self::CATEGORY_OTHER,
    ];

    protected function casts(): array
    {
        return [
            'ends_at' => 'datetime',
            'closed_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'top_checked_at' => 'datetime',
            'feed_score' => 'float',
            'entries_count' => 'integer',
            'votes_count' => 'integer',
            'winner_entry_id' => 'integer',
            'opponent_id' => 'integer',
        ];
    }

    public static function durationMinutes(string $duration): int
    {
        // Challenges created with a since-retired duration still need their deadline computed.
        $minutes = config('versus.challenges.durations.'.$duration)
            ?? config('versus.challenges.legacy_durations.'.$duration);

        if (! is_int($minutes)) {
            throw new InvalidArgumentException("Unknown challenge duration [{$duration}].");
        }

        return $minutes;
    }

    /** Canonical "can people still respond and vote?" check. */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->ends_at !== null
            && now()->lt($this->ends_at);
    }

    public function isDuel(): bool
    {
        return $this->format === self::FORMAT_DUEL;
    }

    /** Whether the user may post a Response: anyone but the creator, or only the opponent in a Duel. */
    public function allowsResponseFrom(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return false;
        }

        return ! $this->isDuel() || $this->opponent_id === $user->id;
    }

    /** @return BelongsTo<User, $this> */
    public function opponent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opponent_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ChallengeEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(ChallengeEntry::class);
    }

    /** @return HasOne<ChallengeEntry, $this> */
    public function original(): HasOne
    {
        return $this->hasOne(ChallengeEntry::class)->where('is_original', true);
    }

    /** @return HasMany<ChallengeEntry, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(ChallengeEntry::class)->where('is_original', false);
    }

    /** @return HasMany<ChallengeVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(ChallengeVote::class);
    }

    /** @return HasMany<ChallengeParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(ChallengeParticipant::class);
    }

    /** @return BelongsTo<ChallengeEntry, $this> */
    public function winnerEntry(): BelongsTo
    {
        return $this->belongsTo(ChallengeEntry::class, 'winner_entry_id');
    }
}

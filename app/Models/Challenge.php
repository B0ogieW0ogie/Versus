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
 */
#[Fillable([
    'slug', 'user_id', 'title', 'rules', 'duration', 'ends_at', 'status',
    'winner_entry_id', 'closed_at', 'reminder_sent_at', 'feed_score',
    'entries_count', 'votes_count',
])]
class Challenge extends Model
{
    /** @use HasFactory<ChallengeFactory> */
    use HasFactory;

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_FAILED = 'failed';

    public const DURATION_24H = '24h';

    public const DURATION_3D = '3d';

    public const DURATION_7D = '7d';

    protected function casts(): array
    {
        return [
            'ends_at' => 'datetime',
            'closed_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'feed_score' => 'float',
            'entries_count' => 'integer',
            'votes_count' => 'integer',
            'winner_entry_id' => 'integer',
        ];
    }

    public static function durationMinutes(string $duration): int
    {
        $minutes = config('versus.challenges.durations.'.$duration);

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

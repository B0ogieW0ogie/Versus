<?php

namespace App\Models;

use Database\Factories\ChallengeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property Carbon $submitted_at
 */
#[Fillable([
    'challenge_id', 'user_id', 'is_original', 'status', 'source_path', 'video_path',
    'poster_path', 'duration_ms', 'failure_reason', 'votes_count', 'likes_count',
    'impressions_count', 'submitted_at', 'top_rank',
])]
class ChallengeEntry extends Model
{
    /** @use HasFactory<ChallengeEntryFactory> */
    use HasFactory;

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'is_original' => 'boolean',
            'submitted_at' => 'datetime',
            'duration_ms' => 'integer',
            'votes_count' => 'integer',
            'likes_count' => 'integer',
            'impressions_count' => 'integer',
            'top_rank' => 'integer',
        ];
    }

    public function videoUrl(): ?string
    {
        return $this->video_path ? Storage::disk('public')->url($this->video_path) : null;
    }

    public function posterUrl(): ?string
    {
        return $this->poster_path ? Storage::disk('public')->url($this->poster_path) : null;
    }

    /** @return BelongsTo<Challenge, $this> */
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<EntryLike, $this> */
    public function likes(): HasMany
    {
        return $this->hasMany(EntryLike::class, 'entry_id');
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'challenge_entry_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $published_at
 * @property array<string, mixed>|null $data
 */
#[Fillable(['type', 'user_id', 'challenge_id', 'entry_id', 'key', 'title', 'body', 'data', 'published_at'])]
class FeedPost extends Model
{
    public const TYPE_STATUS = 'status';

    public const TYPE_ACHIEVEMENT = 'achievement';

    public const TYPE_TOP_CHANGE = 'top_change';

    /** Official VERSUS announcement, written in the admin panel. No user. */
    public const TYPE_VERSUS_NEWS = 'versus_news';

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'data' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Challenge, $this> */
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    /** @return BelongsTo<ChallengeEntry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(ChallengeEntry::class);
    }
}

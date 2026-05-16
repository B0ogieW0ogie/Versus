<?php

namespace App\Models;

use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable(['user_id', 'battle_id', 'parent_id', 'reply_to_user_id', 'body', 'side'])]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Battle, $this>
     */
    public function battle(): BelongsTo
    {
        return $this->belongsTo(Battle::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function replyToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reply_to_user_id');
    }

    /**
     * @return HasMany<CommentLike, $this>
     */
    public function likes(): HasMany
    {
        return $this->hasMany(CommentLike::class);
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * All replies in this thread, depth-first by created_at (single visual indent level).
     *
     * @return Collection<int, self>
     */
    public function flattenedDescendants(): Collection
    {
        $flat = collect();

        $walk = function (self $node) use (&$walk, &$flat): void {
            foreach ($node->children->sortBy('created_at') as $child) {
                $flat->push($child);
                $walk($child);
            }
        };

        $walk($this);

        return $flat;
    }

    /**
     * @param  EloquentCollection<int, self>  $comments
     * @return Collection<int, self>
     */
    public static function buildTree(EloquentCollection $comments): Collection
    {
        $byParent = $comments->groupBy(fn (self $c) => (string) ($c->parent_id ?? ''));

        $attach = function (self $comment) use ($byParent, &$attach): self {
            $children = $byParent->get((string) $comment->id, collect())
                ->sortBy('created_at')
                ->values()
                ->map(fn (self $child) => $attach($child));
            $comment->setRelation('children', $children);

            return $comment;
        };

        return $byParent->get('', collect())
            ->map(fn (self $root) => $attach($root))
            ->values();
    }
}

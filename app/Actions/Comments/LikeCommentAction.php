<?php

namespace App\Actions\Comments;

use App\Actions\Battles\CastVoteAction;
use App\Models\Battle;
use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LikeCommentAction
{
    public function __construct(
        private readonly CastVoteAction $castVote,
    ) {}

    /**
     * @return array{already_liked: bool, liked: bool, likes_count: int}
     */
    public function __invoke(User $user, Comment $comment, Battle $battle): array
    {
        if (! in_array($comment->side, [Battle::SIDE_A, Battle::SIDE_B], true)) {
            throw ValidationException::withMessages(['comment' => __('comments.cannot_like_without_side')]);
        }

        return DB::transaction(function () use ($user, $comment, $battle) {
            $existing = CommentLike::query()
                ->where('user_id', $user->id)
                ->where('comment_id', $comment->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $count = CommentLike::query()
                    ->where('comment_id', $comment->id)
                    ->count();

                return [
                    'already_liked' => true,
                    'liked' => true,
                    'likes_count' => $count,
                ];
            }

            ($this->castVote)($user, $battle, $comment->side, 1.0);

            CommentLike::create([
                'user_id' => $user->id,
                'comment_id' => $comment->id,
            ]);

            $count = CommentLike::query()
                ->where('comment_id', $comment->id)
                ->count();

            return [
                'already_liked' => false,
                'liked' => true,
                'likes_count' => $count,
            ];
        });
    }
}

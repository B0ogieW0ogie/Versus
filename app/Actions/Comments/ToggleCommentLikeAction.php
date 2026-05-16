<?php

namespace App\Actions\Comments;

use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ToggleCommentLikeAction
{
    /**
     * @return array{liked: bool, likes_count: int}
     */
    public function __invoke(User $user, Comment $comment): array
    {
        return DB::transaction(function () use ($user, $comment) {
            $existing = CommentLike::query()
                ->where('user_id', $user->id)
                ->where('comment_id', $comment->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->delete();
                $liked = false;
            } else {
                CommentLike::create([
                    'user_id' => $user->id,
                    'comment_id' => $comment->id,
                ]);
                $liked = true;
            }

            $count = CommentLike::query()
                ->where('comment_id', $comment->id)
                ->count();

            return ['liked' => $liked, 'likes_count' => $count];
        });
    }
}

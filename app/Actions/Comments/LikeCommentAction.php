<?php

namespace App\Actions\Comments;

use App\Models\Battle;
use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\CommentLiked;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class LikeCommentAction
{
    private const float LIKE_AMOUNT = 1.0;

    /**
     * @return array{already_liked: bool, liked: bool, likes_count: int}
     */
    public function __invoke(User $user, Comment $comment, Battle $battle): array
    {
        if (! in_array($comment->side, [Battle::SIDE_A, Battle::SIDE_B], true)) {
            throw ValidationException::withMessages(['comment' => __('comments.cannot_like_without_side')]);
        }

        $result = DB::transaction(function () use ($user, $comment, $battle) {
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

            /** @var Battle $battle */
            $battle = Battle::whereKey($battle->id)->lockForUpdate()->firstOrFail();

            if (! $battle->isOpenForVoting()) {
                throw ValidationException::withMessages(['battle' => __('battle.battle_not_open')]);
            }

            /** @var User $user */
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($user->id === $comment->user_id) {
                throw ValidationException::withMessages(['comment' => __('comments.cannot_like_own')]);
            }

            if ((float) $user->balance < self::LIKE_AMOUNT) {
                throw ValidationException::withMessages(['amount' => __('battle.insufficient_balance')]);
            }

            /** @var User $author */
            $author = User::whereKey($comment->user_id)->lockForUpdate()->firstOrFail();

            $user->balance = $this->round((float) $user->balance - self::LIKE_AMOUNT);
            $user->save();

            $author->balance = $this->round((float) $author->balance + self::LIKE_AMOUNT);
            $author->save();

            Transaction::create([
                'user_id' => $user->id,
                'type' => Transaction::TYPE_COMMENT_LIKE_DEBIT,
                'amount' => -self::LIKE_AMOUNT,
                'balance_after' => $user->balance,
                'battle_id' => $battle->id,
                'meta' => ['comment_id' => $comment->id, 'to_user_id' => $author->id],
            ]);

            Transaction::create([
                'user_id' => $author->id,
                'type' => Transaction::TYPE_COMMENT_LIKE_CREDIT,
                'amount' => self::LIKE_AMOUNT,
                'balance_after' => $author->balance,
                'battle_id' => $battle->id,
                'meta' => ['comment_id' => $comment->id, 'from_user_id' => $user->id],
            ]);

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

        if (! $result['already_liked'] && $comment->user_id !== $user->id) {
            try {
                $comment->user?->notify(new CommentLiked($battle, $comment, $user));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $result;
    }

    private function round(float $value): float
    {
        return round($value, 2);
    }
}

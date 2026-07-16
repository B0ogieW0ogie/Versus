<?php

namespace App\Actions\Comments;

use App\Models\Battle;
use App\Models\Comment;
use App\Models\User;
use App\Notifications\CommentReplied;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PostCommentAction
{
    public function __invoke(
        User $user,
        Battle $battle,
        string $body,
        ?string $side = null,
        ?Comment $parent = null,
        ?User $replyTo = null,
    ): Comment {
        $body = trim($body);

        if ($body === '') {
            throw ValidationException::withMessages(['body' => __('comments.body_required')]);
        }

        if ($side !== null && ! in_array($side, [Battle::SIDE_A, Battle::SIDE_B], true)) {
            throw ValidationException::withMessages(['side' => __('battle.invalid_side')]);
        }

        $comment = DB::transaction(function () use ($user, $battle, $body, $side, $parent, $replyTo) {
            if ($parent !== null) {
                if ($parent->battle_id !== $battle->id) {
                    throw ValidationException::withMessages(['parent' => __('comments.invalid_parent')]);
                }

                $side = $parent->side;
            } elseif ($side === null) {
                $side = null;
            }

            return Comment::create([
                'user_id' => $user->id,
                'battle_id' => $battle->id,
                'parent_id' => $parent?->id,
                'reply_to_user_id' => $replyTo?->id,
                'body' => $body,
                'side' => $side,
            ]);
        });

        $this->notifyReply($user, $battle, $comment, $parent, $replyTo);

        return $comment;
    }

    private function notifyReply(User $actor, Battle $battle, Comment $comment, ?Comment $parent, ?User $replyTo): void
    {
        if ($parent === null) {
            return;
        }

        try {
            $recipients = collect([$parent->user, $replyTo])
                ->filter()
                ->unique('id')
                ->reject(fn (User $recipient): bool => $recipient->id === $actor->id);

            foreach ($recipients as $recipient) {
                $recipient->notify(new CommentReplied($battle, $comment, $actor));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}

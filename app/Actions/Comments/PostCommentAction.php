<?php

namespace App\Actions\Comments;

use App\Models\Battle;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

        return DB::transaction(function () use ($user, $battle, $body, $side, $parent, $replyTo) {
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
    }
}

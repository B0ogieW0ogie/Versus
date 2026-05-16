<?php

namespace App\Actions\Comments;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteCommentAction
{
    public function __invoke(User $user, Comment $comment): void
    {
        if ($comment->trashed()) {
            return;
        }

        if ($comment->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'comment' => __('comments.delete_forbidden'),
            ]);
        }

        DB::transaction(function () use ($comment) {
            $comment->delete();
        });
    }
}

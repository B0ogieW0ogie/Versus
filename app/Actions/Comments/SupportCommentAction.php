<?php

namespace App\Actions\Comments;

use App\Actions\Battles\CastVoteAction;
use App\Models\Battle;
use App\Models\Comment;
use App\Models\User;
use App\Models\Vote;
use App\Notifications\ArgumentSupported;
use Throwable;

class SupportCommentAction
{
    public function __construct(private readonly CastVoteAction $castVote) {}

    public function __invoke(User $user, Battle $battle, Comment $comment, float $amount): Vote
    {
        $vote = ($this->castVote)($user, $battle, (string) $comment->side, $amount);

        $this->notifyAuthor($user, $battle, $comment);

        return $vote;
    }

    private function notifyAuthor(User $actor, Battle $battle, Comment $comment): void
    {
        if ($comment->user_id === $actor->id) {
            return;
        }

        try {
            $author = $comment->user;

            if ($author === null || $this->alreadyNotified($author, $comment, $actor)) {
                return;
            }

            $author->notify(new ArgumentSupported($battle, $comment, $actor));
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function alreadyNotified(User $author, Comment $comment, User $actor): bool
    {
        return $author->notifications()
            ->where('type', ArgumentSupported::class)
            ->where('data->comment_id', $comment->id)
            ->where('data->actor_id', $actor->id)
            ->exists();
    }
}

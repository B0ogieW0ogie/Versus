<?php

namespace App\Actions\Challenges;

use App\Models\ChallengeEntry;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PostEntryCommentAction
{
    public function __invoke(User $user, ChallengeEntry $entry, string $body): Comment
    {
        $body = trim($body);

        if ($body === '' || mb_strlen($body) > (int) config('versus.challenges.comment_max_length')) {
            throw ValidationException::withMessages(['body' => __('challenges.comment_required')]);
        }
        if ($entry->status !== ChallengeEntry::STATUS_READY) {
            throw ValidationException::withMessages(['entry' => __('challenges.entry_not_votable')]);
        }

        return Comment::create([
            'user_id' => $user->id,
            'challenge_entry_id' => $entry->id,
            'body' => $body,
        ]);
    }
}

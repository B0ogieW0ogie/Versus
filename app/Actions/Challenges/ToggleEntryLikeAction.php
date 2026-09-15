<?php

namespace App\Actions\Challenges;

use App\Models\ChallengeEntry;
use App\Models\EntryLike;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ToggleEntryLikeAction
{
    /** @return array{liked: bool, likes_count: int} */
    public function __invoke(User $user, ChallengeEntry $entry): array
    {
        return DB::transaction(function () use ($user, $entry): array {
            /** @var ChallengeEntry $entry */
            $entry = ChallengeEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if ($entry->status !== ChallengeEntry::STATUS_READY) {
                throw ValidationException::withMessages(['entry' => __('challenges.entry_not_votable')]);
            }

            $existing = EntryLike::where('user_id', $user->id)->where('entry_id', $entry->id)->first();

            if ($existing !== null) {
                $existing->delete();
                $entry->likes_count = max(0, $entry->likes_count - 1);
            } else {
                EntryLike::create(['user_id' => $user->id, 'entry_id' => $entry->id]);
                $entry->likes_count++;
            }
            $entry->save();

            return ['liked' => $existing === null, 'likes_count' => $entry->likes_count];
        });
    }
}

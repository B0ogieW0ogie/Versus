<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class ToggleFollowAction
{
    /**
     * Follow $target as $follower if not already following, otherwise unfollow.
     * Self-follow is a no-op. Returns the resulting follow state.
     */
    public function execute(User $follower, User $target): bool
    {
        if ($follower->is($target)) {
            return false;
        }

        return DB::transaction(function () use ($follower, $target): bool {
            if ($follower->isFollowing($target)) {
                $follower->following()->detach($target->getKey());

                return false;
            }

            // syncWithoutDetaching keeps the unique constraint from tripping on a
            // double-submit race; it inserts at most one row for this pair.
            $follower->following()->syncWithoutDetaching([$target->getKey()]);

            return true;
        });
    }
}

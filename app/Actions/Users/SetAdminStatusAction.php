<?php

namespace App\Actions\Users;

use App\Models\User;

class SetAdminStatusAction
{
    public function __invoke(User $user, bool $isAdmin): User
    {
        if ((bool) $user->is_admin === $isAdmin) {
            return $user;
        }

        $user->is_admin = $isAdmin;
        $user->save();

        return $user;
    }
}

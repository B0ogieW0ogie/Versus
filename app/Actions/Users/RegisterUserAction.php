<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterUserAction
{
    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function __invoke(array $attributes, ?string $referralCode = null): User
    {
        return DB::transaction(function () use ($attributes, $referralCode) {
            $referrerId = null;
            if ($referralCode !== null) {
                $referrerId = User::where('referral_code', $referralCode)->value('id');
            }

            $user = User::create([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => Hash::make($attributes['password']),
                'referred_by_id' => $referrerId,
            ]);

            return $user;
        });
    }
}

<?php

use App\Models\User;
use Illuminate\Support\Facades\Blade;

it('ranks a non-admin user as newbie', function () {
    $user = new User;
    $user->is_admin = false;

    expect($user->rank())->toBe(User::RANK_NEWBIE);
});

it('ranks an admin user as admin', function () {
    $user = new User;
    $user->is_admin = true;

    expect($user->rank())->toBe(User::RANK_ADMIN);
});

it('labels the newbie rank via the profile translations', function () {
    $user = new User;
    $user->is_admin = false;

    expect($user->rankLabel())->toBe(__('profile.rank_newbie'));
});

it('labels the admin rank via the profile translations', function () {
    $user = new User;
    $user->is_admin = true;

    expect($user->rankLabel())->toBe(__('profile.rank_admin'));
});

it('renders an admin nickname in orange via the user-name component', function () {
    $user = new User(['name' => 'CryptoKing']);
    $user->is_admin = true;

    $html = Blade::render('<x-user-name :user="$user" class="text-sm" />', ['user' => $user]);

    expect($html)->toContain('CryptoKing')
        ->and($html)->toContain('text-orange-400');
});

it('renders a newbie nickname without the admin orange colour', function () {
    $user = new User(['name' => 'vasya']);
    $user->is_admin = false;

    $html = Blade::render('<x-user-name :user="$user" class="text-white/90" />', ['user' => $user]);

    expect($html)->toContain('vasya')
        ->and($html)->not->toContain('text-orange-400');
});

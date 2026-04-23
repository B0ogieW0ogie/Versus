<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('avatarUrl returns null when avatar_path is null', function () {
    $user = User::factory()->create(['avatar_path' => null]);

    expect($user->avatarUrl())->toBeNull();
});

test('avatarUrl returns public storage url when path is set', function () {
    Storage::fake('public');
    $user = User::factory()->create(['avatar_path' => 'avatars/a.png']);

    expect($user->avatarUrl())->toBe(Storage::disk('public')->url('avatars/a.png'));
});

test('bannerUrl mirrors avatarUrl behaviour', function () {
    Storage::fake('public');
    $user = User::factory()->create(['banner_path' => 'banners/b.png']);

    expect($user->bannerUrl())->toBe(Storage::disk('public')->url('banners/b.png'));
});

<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('users table has new profile columns', function () {
    $user = User::factory()->create([
        'username' => 'alice',
        'bio' => 'hello',
        'avatar_path' => 'avatars/a.png',
        'banner_path' => 'banners/b.png',
    ]);

    expect($user->fresh())
        ->username->toBe('alice')
        ->bio->toBe('hello')
        ->avatar_path->toBe('avatars/a.png')
        ->banner_path->toBe('banners/b.png');
});

test('username must be unique', function () {
    User::factory()->create(['username' => 'alice']);

    expect(fn () => User::factory()->create(['username' => 'alice']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can set username and bio', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile/settings', [
            'name' => $user->name,
            'email' => $user->email,
            'username' => 'alice_99',
            'bio' => 'Just a test',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile/settings');

    $user->refresh();
    expect($user->username)->toBe('alice_99');
    expect($user->bio)->toBe('Just a test');
});

test('username must be unique', function () {
    User::factory()->create(['username' => 'taken']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/profile/settings')
        ->patch('/profile/settings', [
            'name' => $user->name,
            'email' => $user->email,
            'username' => 'taken',
        ])
        ->assertSessionHasErrors('username');
});

test('username rejects invalid characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/profile/settings')
        ->patch('/profile/settings', [
            'name' => $user->name,
            'email' => $user->email,
            'username' => 'has space',
        ])
        ->assertSessionHasErrors('username');
});

test('username may be unset by sending empty value', function () {
    $user = User::factory()->create(['username' => 'alice']);

    $this->actingAs($user)
        ->patch('/profile/settings', [
            'name' => $user->name,
            'email' => $user->email,
            'username' => '',
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->username)->toBeNull();
});

test('bio is saved and rendered on profile page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile/settings', [
            'name' => $user->name,
            'email' => $user->email,
            'bio' => "multi\nline bio",
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($user->fresh())
        ->get('/profile')
        ->assertSee('multi')
        ->assertSee('line bio');
});

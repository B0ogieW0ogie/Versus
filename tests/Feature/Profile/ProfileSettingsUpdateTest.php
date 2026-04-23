<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

test('user can upload an avatar', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile/settings', [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('me.png'),
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->avatar_path)->toStartWith('avatars/');
    Storage::disk('public')->assertExists($user->avatar_path);
});

test('user can upload a banner', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile/settings', [
            'name' => $user->name,
            'email' => $user->email,
            'banner' => UploadedFile::fake()->image('banner.png'),
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->banner_path)->toStartWith('banners/');
    Storage::disk('public')->assertExists($user->banner_path);
});

test('uploading a new avatar deletes the previous file', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    // First upload
    $this->actingAs($user)->patch('/profile/settings', [
        'name' => $user->name,
        'email' => $user->email,
        'avatar' => UploadedFile::fake()->image('first.png'),
    ]);
    $firstPath = $user->fresh()->avatar_path;
    Storage::disk('public')->assertExists($firstPath);

    // Second upload
    $this->actingAs($user->fresh())->patch('/profile/settings', [
        'name' => $user->name,
        'email' => $user->email,
        'avatar' => UploadedFile::fake()->image('second.png'),
    ]);

    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($user->fresh()->avatar_path);
});

test('non-image files are rejected', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/profile/settings')
        ->patch('/profile/settings', [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('avatar');
});

<?php

use App\Livewire\ProfilePage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('renders for authenticated user', function () {
    $user = User::factory()->create(['name' => 'Alice']);

    Livewire::actingAs($user)
        ->test(ProfilePage::class)
        ->assertOk()
        ->assertSee('Alice');
});

test('guest is redirected to login', function () {
    $this->get('/profile')->assertRedirect(route('login'));
});

test('authenticated user sees the profile page at /profile', function () {
    $user = User::factory()->create(['name' => 'Alice']);

    $this->actingAs($user)
        ->get('/profile')
        ->assertOk()
        ->assertSee('Alice');
});

test('shows user name, handle, bio and hardcoded stats', function () {
    $user = User::factory()->create([
        'name' => 'Vlad Basargin',
        'username' => 'vladbasargin',
        'bio' => 'Люблю спорить о футболе',
    ]);

    $this->actingAs($user)
        ->get('/profile')
        ->assertSee('Vlad Basargin')
        ->assertSee('@vladbasargin')
        ->assertSee('Architect of Reality')
        ->assertSee('Люблю спорить о футболе', escape: false)
        ->assertSee('352')
        ->assertSee('128')
        ->assertSee('2,450');
});

test('falls back to @user{id} when username is null', function () {
    $user = User::factory()->create(['username' => null]);

    $this->actingAs($user)
        ->get('/profile')
        ->assertSee('@user'.$user->id);
});

test('edit button links to profile settings route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/profile')
        ->assertSee(route('profile.settings'), escape: false);
});

test('activity tab is active by default', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ProfilePage::class)
        ->assertSet('tab', 'activity');
});

test('tab is switchable via url', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->withQueryParams(['tab' => 'comments'])
        ->test(ProfilePage::class)
        ->assertSet('tab', 'comments');
});

test('invalid tab param falls back to activity', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->withQueryParams(['tab' => 'garbage'])
        ->test(ProfilePage::class)
        ->assertSet('tab', 'activity');
});

test('creation tab shows coming soon', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/profile?tab=creation')
        ->assertSee(__('profile.coming_soon'));
});

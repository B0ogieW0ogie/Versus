<?php

use App\Livewire\VerifiedWelcomePage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('guest cannot access welcome', function () {
    $this->get(route('welcome'))->assertRedirect(route('login'));
});

test('unverified user cannot access welcome', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('welcome'))
        ->assertRedirect(route('verification.notice', absolute: false));
});

test('verified user without session flag is redirected home', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(VerifiedWelcomePage::class)
        ->assertRedirect(route('home'));
});

test('welcome screen renders when session flag is set', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    session(['show_verified_welcome' => true]);

    Livewire::test(VerifiedWelcomePage::class)
        ->assertOk()
        ->assertSee(__('welcome.title'));
});

test('start clears session and redirects home', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    session(['show_verified_welcome' => true]);

    Livewire::test(VerifiedWelcomePage::class)
        ->set('slide', 2)
        ->call('start')
        ->assertRedirect(route('home'));

    expect(session()->get('show_verified_welcome'))->toBeNull();
});

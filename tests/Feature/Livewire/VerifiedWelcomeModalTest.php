<?php

use App\Livewire\VerifiedWelcomeModal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('modal stays closed without session flag', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(VerifiedWelcomeModal::class)
        ->assertSet('open', false)
        ->assertDontSee(__('welcome.title'));
});

test('modal opens when session flag is set', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    session(['show_verified_welcome' => true]);

    Livewire::test(VerifiedWelcomeModal::class)
        ->assertSet('open', true)
        ->assertSee(__('welcome.title'));
});

test('home page includes welcome title when session flag is set', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    session(['show_verified_welcome' => true]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(__('welcome.title'));
});

test('start closes modal clears session and starts onboarding on profile', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    session(['show_verified_welcome' => true]);

    Livewire::test(VerifiedWelcomeModal::class)
        ->set('slide', 2)
        ->call('start')
        ->assertRedirect(route('profile.edit'));

    expect(session()->get('show_verified_welcome'))->toBeNull();

    $user->refresh();
    expect($user->is_first_visit)->toBeTrue()
        ->and($user->onboarding_step)->toBe(0);
});

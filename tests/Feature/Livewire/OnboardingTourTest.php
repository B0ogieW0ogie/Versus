<?php

use App\Livewire\OnboardingTour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('onboarding tour hidden when not active', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(OnboardingTour::class)
        ->assertDontSee(__('onboarding.next'));
});

test('advance moves from step zero to one', function () {
    $user = User::factory()->create([
        'is_first_visit' => true,
        'onboarding_step' => 0,
    ]);

    Livewire::actingAs($user)
        ->test(OnboardingTour::class)
        ->assertSee(__('onboarding.steps.0.body'))
        ->call('advance');

    expect($user->fresh()->onboarding_step)->toBe(1);
});

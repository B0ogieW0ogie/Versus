<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('/my-bets redirects to /profile?tab=activity', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/my-bets')
        ->assertRedirect('/profile?tab=activity');
});

test('/referrals redirects to /profile?tab=referrals', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/referrals')
        ->assertRedirect('/profile?tab=referrals');
});

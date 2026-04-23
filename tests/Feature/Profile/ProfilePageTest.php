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

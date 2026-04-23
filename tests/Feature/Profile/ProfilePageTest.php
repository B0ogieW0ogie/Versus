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

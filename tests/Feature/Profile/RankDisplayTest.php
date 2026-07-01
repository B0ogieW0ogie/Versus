<?php

use App\Livewire\ConnectionsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('connections list renders an admin follower nickname in orange', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create();
    $admin = User::factory()->create(['name' => 'AdminGuy', 'is_admin' => true]);
    $target->followers()->attach($admin);

    Livewire::actingAs($viewer)
        ->test(ConnectionsPage::class, ['user' => $target, 'type' => 'subscribers'])
        ->assertSee('AdminGuy')
        ->assertSee('text-orange-400', escape: false);
});

test('connections list does not colour a newbie follower nickname', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create();
    $newbie = User::factory()->create(['name' => 'PlainJane', 'is_admin' => false]);
    $target->followers()->attach($newbie);

    Livewire::actingAs($viewer)
        ->test(ConnectionsPage::class, ['user' => $target, 'type' => 'subscribers'])
        ->assertSee('PlainJane')
        ->assertDontSee('text-orange-400', escape: false);
});

test('profile page shows the Admin rank label in orange for an admin', function () {
    $viewer = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($viewer)
        ->get(route('profile.show', $admin))
        ->assertOk()
        ->assertSee(__('profile.rank_admin'))
        ->assertSee('text-orange-400', escape: false);
});

test('profile page shows the Newbie rank label for a non-admin', function () {
    $viewer = User::factory()->create();
    $newbie = User::factory()->create(['is_admin' => false]);

    $this->actingAs($viewer)
        ->get(route('profile.show', $newbie))
        ->assertOk()
        ->assertSee(__('profile.rank_newbie'))
        ->assertDontSee('text-orange-400', escape: false);
});

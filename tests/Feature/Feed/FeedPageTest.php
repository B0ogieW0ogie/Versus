<?php

use App\Livewire\FeedPage;
use App\Models\Battle;
use App\Models\User;
use App\Models\Vote;
use App\Services\Feed\FeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('feed page renders for an authenticated user', function () {
    $viewer = User::factory()->create();
    $this->actingAs($viewer);

    Livewire::test(FeedPage::class)->assertOk()->assertSet('filter', FeedService::FILTER_ALL);
});

test('set filter validates and updates state', function () {
    $viewer = User::factory()->create();
    $this->actingAs($viewer);

    Livewire::test(FeedPage::class)
        ->call('setFilter', FeedService::FILTER_VOTES)
        ->assertSet('filter', FeedService::FILTER_VOTES)
        ->call('setFilter', 'bogus')
        ->assertSet('filter', FeedService::FILTER_VOTES);
});

test('load more grows the number of events shown', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $viewer->following()->attach($author->id);
    $this->actingAs($viewer);

    // 20 separate vote events (each in its own battle, so no grouping collapse).
    Vote::factory()->count(20)->create(['user_id' => $author->id]);

    $component = Livewire::test(FeedPage::class);
    $component->assertSet('hasMore', true);

    $component->call('loadMore')->assertSet('pages', 2);
});

test('settled battle result renders a win headline on the page', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create(['username' => 'morpheus', 'name' => 'Morpheus']);
    $viewer->following()->attach($author->id);
    $this->actingAs($viewer);

    $battle = Battle::factory()->settled(Battle::SIDE_A)->create();
    Vote::factory()->create(['user_id' => $author->id, 'battle_id' => $battle->id, 'side' => Battle::SIDE_A]);

    Livewire::test(FeedPage::class)
        ->call('setFilter', FeedService::FILTER_RESULTS)
        ->assertSee('morpheus')
        ->assertDontSee('@morpheus');
});

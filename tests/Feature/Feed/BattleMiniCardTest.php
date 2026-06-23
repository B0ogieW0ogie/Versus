<?php

use App\Models\Battle;
use App\Models\User;
use App\Services\Feed\FeedEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;

uses(RefreshDatabase::class);

test('battle mini card shows titled labels and compact pool', function () {
    $battle = Battle::factory()->create([
        'side_a_label' => 'cats',
        'side_b_label' => 'dogs',
        'total_pool' => 1500,
    ]);

    $html = Blade::render('<x-battle-mini-card :battle="$battle" />', ['battle' => $battle]);

    expect($html)->toContain('Cats')
        ->toContain('Dogs')
        ->toContain('1.5k');
});

test('battle mini card shows a timer for an open battle', function () {
    $battle = Battle::factory()->create([
        'status' => Battle::STATUS_ACTIVE,
        'opens_at' => now()->subHour(),
        'closes_at' => now()->addHours(5),
    ]);

    $html = Blade::render('<x-battle-mini-card :battle="$battle" />', ['battle' => $battle]);

    expect($html)->toContain('⏱');
});

test('battle mini card hides the timer for a settled battle', function () {
    $battle = Battle::factory()->settled(Battle::SIDE_A)->create();

    $html = Blade::render('<x-battle-mini-card :battle="$battle" />', ['battle' => $battle]);

    expect($html)->not->toContain('⏱');
});

test('event card renders a win headline with a clickable name and no @', function () {
    app()->setLocale('en');

    $actor = User::factory()->create(['username' => 'neo', 'name' => 'Neo']);
    $battle = Battle::factory()->settled(Battle::SIDE_A)->create();
    $event = new FeedEvent(
        FeedEvent::TYPE_WIN,
        $actor,
        $battle,
        $battle->settled_at,
    );

    $html = Blade::render('<x-feed.event-card :event="$event" />', ['event' => $event]);

    expect($html)->toContain('neo')
        ->toContain('won the battle')
        ->not->toContain('@neo')
        ->toContain(route('profile.show', $actor))
        ->toContain('VIEW BATTLE');
});

test('event card renders a like headline with the argument quote', function () {
    app()->setLocale('en');

    $actor = User::factory()->create(['username' => 'trinity', 'name' => 'Trinity']);
    $battle = Battle::factory()->create();
    $event = new FeedEvent(
        FeedEvent::TYPE_LIKE,
        $actor,
        $battle,
        now(),
        'There is no spoon',
    );

    $html = Blade::render('<x-feed.event-card :event="$event" />', ['event' => $event]);

    expect($html)->toContain('liked an argument')
        ->toContain('There is no spoon');
});

test('event card shows battle ended for a vote on a closed battle', function () {
    app()->setLocale('en');

    $actor = User::factory()->create(['username' => 'cypher', 'name' => 'Cypher']);
    $battle = Battle::factory()->closed()->create();
    $event = new FeedEvent(
        FeedEvent::TYPE_VOTE,
        $actor,
        $battle,
        now(),
    );

    $html = Blade::render('<x-feed.event-card :event="$event" />', ['event' => $event]);

    expect($html)->toContain('BATTLE ENDED')
        ->not->toContain('VOTE WITH');
});

test('event card names the chosen side in a vote headline', function () {
    app()->setLocale('en');

    $actor = User::factory()->create(['username' => 'cypher', 'name' => 'Cypher']);
    $battle = Battle::factory()->create(['side_a_label' => 'cats', 'side_b_label' => 'dogs']);
    $event = new FeedEvent(
        FeedEvent::TYPE_VOTE,
        $actor,
        $battle,
        now(),
        null,
        'dogs',
    );

    $html = Blade::render('<x-feed.event-card :event="$event" />', ['event' => $event]);

    expect($html)->toContain('voted for Dogs in the battle');
});

test('battle mini card shows winner and loser badges for a settled battle', function () {
    app()->setLocale('en');

    $battle = Battle::factory()->settled(Battle::SIDE_A)->create([
        'side_a_label' => 'cats',
        'side_b_label' => 'dogs',
    ]);

    $html = Blade::render('<x-battle-mini-card :battle="$battle" />', ['battle' => $battle]);

    expect($html)->toContain('WINNER')
        ->toContain('LOSER');
});

test('battle mini card shows no outcome badges for a tie', function () {
    app()->setLocale('en');

    $battle = Battle::factory()->create([
        'status' => Battle::STATUS_SETTLED,
        'settled_at' => now(),
        'winning_side' => null,
    ]);

    $html = Blade::render('<x-battle-mini-card :battle="$battle" />', ['battle' => $battle]);

    expect($html)->not->toContain('WINNER')
        ->not->toContain('LOSER');
});

test('battle mini card shows no outcome badges while a battle is open', function () {
    app()->setLocale('en');

    $battle = Battle::factory()->create([
        'status' => Battle::STATUS_ACTIVE,
        'opens_at' => now()->subHour(),
        'closes_at' => now()->addHours(5),
    ]);

    $html = Blade::render('<x-battle-mini-card :battle="$battle" />', ['battle' => $battle]);

    expect($html)->not->toContain('WINNER')
        ->not->toContain('LOSER');
});

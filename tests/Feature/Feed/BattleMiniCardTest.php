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

test('event card renders a win headline and view-battle CTA', function () {
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

    expect($html)->toContain('@neo')
        ->toContain('@neo won')
        ->toContain('VIEW BATTLE');
});

test('event card renders an argument quote for a grouped vote+argue', function () {
    app()->setLocale('en');

    $actor = User::factory()->create(['username' => 'trinity', 'name' => 'Trinity']);
    $battle = Battle::factory()->create();
    $event = new FeedEvent(
        FeedEvent::TYPE_VOTE_ARGUE,
        $actor,
        $battle,
        now(),
        'There is no spoon',
    );

    $html = Blade::render('<x-feed.event-card :event="$event" />', ['event' => $event]);

    expect($html)->toContain('@trinity votes and argues')
        ->toContain('There is no spoon')
        ->toContain('VOTE WITH');
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

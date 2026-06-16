<?php

use App\Models\Battle;
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

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

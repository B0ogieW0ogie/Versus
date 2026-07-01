<?php

namespace Tests\Feature\Battles;

use App\Livewire\BattleIndex;
use App\Models\Battle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BattleIndexHotTest extends TestCase
{
    use RefreshDatabase;

    public function test_hot_pins_last_shot_battles_ahead_of_active(): void
    {
        $active = Battle::factory()->create([
            'status' => Battle::STATUS_ACTIVE,
            'total_pool' => 5000,
            'is_sponsored' => false,
        ]);
        $lastShot = Battle::factory()->create([
            'status' => Battle::STATUS_LAST_SHOT,
            'total_pool' => 10,
            'is_sponsored' => false,
        ]);

        Livewire::test(BattleIndex::class)
            ->assertViewHas('hot', function ($hot) use ($active, $lastShot) {
                return $hot->contains(fn ($b) => $b->is($lastShot))
                    && $hot->contains(fn ($b) => $b->is($active))
                    && $hot->first()->is($lastShot);
            });
    }
}

<?php

namespace Tests\Feature;

use App\Actions\Battles\AddBattlePoolAction;
use App\Models\Battle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattlePoolTotalTest extends TestCase
{
    use RefreshDatabase;

    public function test_pool_total_endpoint_returns_battle_total_pool(): void
    {
        $battle = Battle::factory()->create([
            'slug' => 'test-pool-slug',
            'total_pool' => 150.5,
        ]);

        $response = $this->getJson(route('battles.pool-total', $battle));

        $response->assertOk()
            ->assertJson(['total' => 150.5]);
    }

    public function test_pool_total_endpoint_includes_admin_boosts(): void
    {
        $battle = Battle::factory()->create(['slug' => 'boosted-pool', 'total_pool' => 0]);

        app(AddBattlePoolAction::class)($battle, 100_000);

        $response = $this->getJson(route('battles.pool-total', $battle));

        $response->assertOk()
            ->assertJson(['total' => 100_000]);
    }
}

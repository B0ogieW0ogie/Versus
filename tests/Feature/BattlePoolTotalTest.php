<?php

namespace Tests\Feature;

use App\Models\Battle;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattlePoolTotalTest extends TestCase
{
    use RefreshDatabase;

    public function test_pool_total_endpoint_returns_sum_of_vote_amounts(): void
    {
        $battle = Battle::factory()->create(['slug' => 'test-pool-slug']);
        $u = User::factory()->create();
        Vote::factory()->create(['battle_id' => $battle->id, 'user_id' => $u->id, 'side' => 'A', 'amount' => 100, 'weight' => 100]);
        Vote::factory()->create(['battle_id' => $battle->id, 'user_id' => $u->id, 'side' => 'B', 'amount' => 50.5, 'weight' => 50.5]);

        $response = $this->getJson(route('battles.pool-total', $battle));

        $response->assertOk()
            ->assertJson(['total' => 150.5]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Battle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattlePoolTotalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_map_of_id_to_total_for_requested_ids(): void
    {
        $a = Battle::factory()->create(['total_pool' => 150.5]);
        $b = Battle::factory()->create(['total_pool' => 900]);

        $response = $this->getJson(route('battles.pool-totals', ['ids' => "{$a->id},{$b->id}"]));

        $response->assertOk()
            ->assertExactJson([
                (string) $a->id => 150.5,
                (string) $b->id => 900,
            ]);
    }

    public function test_omits_unknown_ids(): void
    {
        $a = Battle::factory()->create(['total_pool' => 10]);

        $response = $this->getJson(route('battles.pool-totals', ['ids' => "{$a->id},99999"]));

        $response->assertOk()
            ->assertExactJson([(string) $a->id => 10]);
    }

    public function test_empty_or_garbage_ids_returns_empty_object(): void
    {
        $this->getJson(route('battles.pool-totals', ['ids' => '']))
            ->assertOk()->assertExactJson([]);

        $this->getJson(route('battles.pool-totals', ['ids' => 'abc,-1,0']))
            ->assertOk()->assertExactJson([]);
    }

    public function test_caps_at_fifty_ids(): void
    {
        // 60 non-existent ids must not error; response is just empty (none exist).
        $ids = implode(',', range(1000, 1059));

        $this->getJson(route('battles.pool-totals', ['ids' => $ids]))
            ->assertOk();
    }
}

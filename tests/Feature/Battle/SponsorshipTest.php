<?php

namespace Tests\Feature\Battle;

use App\Models\Battle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SponsorshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_sponsored_active_returns_only_active_sponsored_battles(): void
    {
        Battle::factory()->create(['is_sponsored' => true]);
        Battle::factory()->create(['is_sponsored' => false]);
        Battle::factory()->settled()->create(['is_sponsored' => true]);
        Battle::factory()->draft()->create(['is_sponsored' => true]);

        $result = Battle::sponsoredActive();

        $this->assertCount(1, $result);
    }

    public function test_sponsored_active_orders_by_closes_at_ascending(): void
    {
        $later = Battle::factory()->create([
            'is_sponsored' => true,
            'closes_at' => now()->addDays(5),
        ]);
        $sooner = Battle::factory()->create([
            'is_sponsored' => true,
            'closes_at' => now()->addDays(1),
        ]);

        $result = Battle::sponsoredActive();

        $this->assertTrue($result->first()->is($sooner));
        $this->assertTrue($result->last()->is($later));
    }

    public function test_sponsored_active_limits_to_ten(): void
    {
        Battle::factory()->count(12)->create(['is_sponsored' => true]);

        $this->assertCount(10, Battle::sponsoredActive());
    }

    public function test_compact_pool_formats_thousands_with_k(): void
    {
        $b = Battle::factory()->make(['total_pool' => 45000]);
        $this->assertSame('45k', $b->compactPool());
    }

    public function test_compact_pool_formats_non_round_thousands_with_one_decimal(): void
    {
        $b = Battle::factory()->make(['total_pool' => 1500]);
        $this->assertSame('1.5k', $b->compactPool());
    }

    public function test_compact_pool_returns_plain_number_under_thousand(): void
    {
        $b = Battle::factory()->make(['total_pool' => 420]);
        $this->assertSame('420', $b->compactPool());
    }

    public function test_is_sponsored_casts_to_boolean(): void
    {
        $b = Battle::factory()->create(['is_sponsored' => true]);
        $this->assertSame(true, $b->fresh()->is_sponsored);
    }

    public function test_sponsor_handle_is_fillable(): void
    {
        $b = Battle::factory()->create(['sponsor_handle' => '@brand']);
        $this->assertSame('@brand', $b->fresh()->sponsor_handle);
    }
}

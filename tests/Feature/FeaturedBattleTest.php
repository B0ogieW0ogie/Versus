<?php

namespace Tests\Feature;

use App\Models\Battle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeaturedBattleTest extends TestCase
{
    use RefreshDatabase;

    public function test_flagged_battle_beats_bigger_pool(): void
    {
        Battle::query()->delete();
        Battle::factory()->create(['total_pool' => 9999, 'is_featured' => false]);
        $flagged = Battle::factory()->create(['total_pool' => 100, 'is_featured' => true]);

        $this->assertTrue(Battle::resolveFeatured()->is($flagged));
    }

    public function test_fallback_picks_biggest_active_pool_when_none_flagged(): void
    {
        Battle::query()->delete();
        Battle::factory()->create(['total_pool' => 500]);
        $biggest = Battle::factory()->create(['total_pool' => 2000]);
        Battle::factory()->create(['total_pool' => 100]);

        $this->assertTrue(Battle::resolveFeatured()->is($biggest));
    }

    public function test_most_recently_updated_flag_wins_when_multiple(): void
    {
        Battle::query()->delete();
        $older = Battle::factory()->create(['is_featured' => true]);
        $older->forceFill(['updated_at' => now()->subHour()])->save();
        $newer = Battle::factory()->create(['is_featured' => true]);
        $newer->forceFill(['updated_at' => now()])->save();

        $this->assertTrue(Battle::resolveFeatured()->is($newer));
    }

    public function test_settled_battles_are_never_featured(): void
    {
        Battle::query()->delete();
        Battle::factory()->settled()->create(['is_featured' => true, 'total_pool' => 999]);
        $active = Battle::factory()->create(['total_pool' => 10]);

        $this->assertTrue(Battle::resolveFeatured()->is($active));
    }

    public function test_returns_null_when_no_active_battles(): void
    {
        Battle::query()->delete();
        Battle::factory()->settled()->create(['is_featured' => true]);
        Battle::factory()->draft()->create();

        $this->assertNull(Battle::resolveFeatured());
    }
}

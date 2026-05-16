<?php

namespace Tests\Feature;

use App\Models\Battle;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattleScopesTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_scope_returns_only_active_battles(): void
    {
        Battle::query()->delete();

        Battle::factory()->create(['status' => Battle::STATUS_ACTIVE]);
        Battle::factory()->draft()->create();
        Battle::factory()->settled()->create();

        $this->assertSame(1, Battle::query()->active()->count());
    }

    public function test_category_relation(): void
    {
        $cat = Category::factory()->create();
        $battle = Battle::factory()->create(['category_id' => $cat->id]);

        $this->assertTrue($battle->category->is($cat));
    }

    public function test_is_open_for_voting_false_when_opens_at_is_in_the_future(): void
    {
        $battle = Battle::factory()->create([
            'status' => Battle::STATUS_ACTIVE,
            'opens_at' => now()->addHour(),
            'closes_at' => now()->addDay(),
        ]);

        $this->assertFalse($battle->isOpenForVoting());
    }

    public function test_is_open_for_voting_true_when_opens_at_is_null(): void
    {
        $battle = Battle::factory()->create([
            'status' => Battle::STATUS_ACTIVE,
            'opens_at' => null,
            'closes_at' => now()->addDay(),
        ]);

        $this->assertTrue($battle->isOpenForVoting());
    }
}

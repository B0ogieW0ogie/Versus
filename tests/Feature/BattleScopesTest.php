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
}

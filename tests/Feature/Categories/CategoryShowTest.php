<?php

namespace Tests\Feature\Categories;

use App\Models\Battle;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_active_battles_for_the_category(): void
    {
        $cat = Category::factory()->create(['slug' => 'sports']);
        $active = Battle::factory()->create(['category_id' => $cat->id]);
        Battle::factory()->settled()->create(['category_id' => $cat->id]);

        $this->get(route('categories.show', $cat))
            ->assertOk()
            ->assertSee($active->title);
    }

    public function test_shows_empty_state_when_no_active_battles(): void
    {
        $cat = Category::factory()->create(['slug' => 'memes']);
        Battle::factory()->settled()->create(['category_id' => $cat->id]);

        $this->get(route('categories.show', $cat))
            ->assertOk()
            ->assertSee(__('battle.no_active_in_category'));
    }

    public function test_returns_404_for_unknown_slug(): void
    {
        $this->get('/categories/does-not-exist')->assertNotFound();
    }
}

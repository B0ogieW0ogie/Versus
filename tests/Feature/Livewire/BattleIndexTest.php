<?php

namespace Tests\Feature\Livewire;

use App\Livewire\BattleIndex;
use App\Models\Battle;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BattleIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_render_surfaces_featured_hot_and_all(): void
    {
        Battle::query()->delete();

        $featured = Battle::factory()->create(['total_pool' => 500, 'is_featured' => true]);
        Battle::factory()->create(['total_pool' => 400]);
        Battle::factory()->create(['total_pool' => 300]);
        Battle::factory()->create(['total_pool' => 200]);
        Battle::factory()->create(['total_pool' => 100]);

        Livewire::test(BattleIndex::class)
            ->assertViewHas('featured', fn ($v) => $v !== null && $v->is($featured))
            ->assertViewHas('hot', fn ($hot) => $hot->count() === 3 && ! $hot->contains('id', $featured->id))
            ->assertViewHas('all', fn ($all) => $all->count() === 1);
    }

    public function test_selecting_a_category_filters_all_list(): void
    {
        Battle::query()->delete();

        $sports = Category::factory()->create(['slug' => 'sports']);
        $memes = Category::factory()->create(['slug' => 'memes']);

        Battle::factory()->create(['category_id' => $sports->id, 'total_pool' => 50]);
        Battle::factory()->create(['category_id' => $sports->id, 'total_pool' => 40]);
        Battle::factory()->create(['category_id' => $memes->id, 'total_pool' => 30]);

        // Featured + 3 in Hot top-3 (all uncategorized) to push sports/memes into All:
        Battle::factory()->create(['total_pool' => 1000]);
        Battle::factory()->create(['total_pool' => 900]);
        Battle::factory()->create(['total_pool' => 800]);
        Battle::factory()->create(['total_pool' => 700]);

        Livewire::test(BattleIndex::class)
            ->call('selectCategory', 'sports')
            ->assertViewHas('all', fn ($all) => $all->count() === 2);
    }

    public function test_toggle_finished_hides_featured_and_shows_settled(): void
    {
        Battle::query()->delete();

        Battle::factory()->create(['is_featured' => true, 'total_pool' => 100]);
        Battle::factory()->settled()->create();
        Battle::factory()->settled()->create();

        Livewire::test(BattleIndex::class)
            ->call('toggleFinished')
            ->assertViewHas('featured', null)
            ->assertViewHas('hot', fn ($hot) => $hot->isEmpty())
            ->assertViewHas('all', fn ($all) => $all->count() === 2);
    }

    public function test_empty_state_when_no_battles(): void
    {
        Battle::query()->delete();

        Livewire::test(BattleIndex::class)
            ->assertViewHas('featured', null)
            ->assertViewHas('hot', fn ($hot) => $hot->isEmpty())
            ->assertViewHas('all', fn ($all) => $all->count() === 0)
            ->assertSee(__('battle.no_battles'));
    }

    public function test_clear_filters_resets_category_and_finished(): void
    {
        Livewire::test(BattleIndex::class)
            ->set('category', 'sports')
            ->set('finished', true)
            ->call('clearFilters')
            ->assertSet('category', null)
            ->assertSet('finished', false);
    }
}

<?php

namespace Tests\Feature\Home;

use App\Livewire\BattleIndex;
use App\Models\Battle;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HomePageSectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_only_active_sponsored_battles_in_slider(): void
    {
        $active = Battle::factory()->sponsored()->create();
        Battle::factory()->settled()->create(['is_sponsored' => true]);
        Battle::factory()->create(['is_sponsored' => false]);

        Livewire::test(BattleIndex::class)
            ->assertViewHas('sponsored', fn ($s) => $s->count() === 1 && $s->first()->is($active));
    }

    public function test_hot_rail_is_ordered_by_total_pool_desc_and_limited_to_ten(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            Battle::factory()->create(['total_pool' => $i * 100]);
        }

        Livewire::test(BattleIndex::class)
            ->assertViewHas('hot', fn ($hot) => $hot->count() === 10
                && (int) $hot->first()->total_pool === 1200
                && (int) $hot->last()->total_pool === 300);
    }

    public function test_sponsored_battles_are_excluded_from_hot_rail(): void
    {
        $sponsored = Battle::factory()->sponsored()->create(['total_pool' => 9999]);
        Battle::factory()->create(['total_pool' => 100]);

        Livewire::test(BattleIndex::class)
            ->assertViewHas('hot', fn ($hot) => ! $hot->contains('id', $sponsored->id));
    }

    public function test_category_rails_are_ordered_by_sort_order(): void
    {
        $c1 = Category::factory()->create(['slug' => 'one', 'sort_order' => 10]);
        $c2 = Category::factory()->create(['slug' => 'two', 'sort_order' => 20]);
        Battle::factory()->create(['category_id' => $c1->id]);
        Battle::factory()->create(['category_id' => $c2->id]);

        Livewire::test(BattleIndex::class)
            ->assertViewHas('categoryRails', function ($rails) {
                $slugs = $rails->pluck('slug')->values()->all();

                return $slugs === ['one', 'two'];
            });
    }

    public function test_empty_category_rails_are_hidden(): void
    {
        $withBattles = Category::factory()->create(['sort_order' => 10]);
        Category::factory()->create(['sort_order' => 20]); // empty
        Battle::factory()->create(['category_id' => $withBattles->id]);

        Livewire::test(BattleIndex::class)
            ->assertViewHas('categoryRails', fn ($rails) => $rails->count() === 1
                && $rails->first()->is($withBattles));
    }

    public function test_sponsored_battles_are_excluded_from_category_rails(): void
    {
        $c = Category::factory()->create();
        $sponsored = Battle::factory()->sponsored()->create([
            'category_id' => $c->id,
            'total_pool' => 9999,
        ]);
        $regular = Battle::factory()->create([
            'category_id' => $c->id,
            'total_pool' => 50,
        ]);

        Livewire::test(BattleIndex::class)
            ->assertViewHas('categoryRails', function ($rails) use ($sponsored, $regular) {
                $rail = $rails->first();
                $ids = $rail->battles->pluck('id')->all();

                return in_array($regular->id, $ids, true)
                    && ! in_array($sponsored->id, $ids, true);
            });
    }
}

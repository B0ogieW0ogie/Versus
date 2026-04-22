<?php

namespace Tests\Feature\Livewire;

use App\Livewire\SearchOverlay;
use App\Models\Battle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SearchOverlayTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_query_returns_no_results(): void
    {
        Battle::factory()->create(['title' => 'Messi vs Ronaldo']);

        Livewire::test(SearchOverlay::class)
            ->assertViewHas('results', fn ($r) => $r->isEmpty());
    }

    public function test_single_char_query_returns_no_results(): void
    {
        Battle::factory()->create(['title' => 'Messi vs Ronaldo']);

        Livewire::test(SearchOverlay::class)
            ->set('query', 'M')
            ->assertViewHas('results', fn ($r) => $r->isEmpty());
    }

    public function test_matches_title_case_insensitively(): void
    {
        $match = Battle::factory()->create(['title' => 'Messi vs Ronaldo']);
        Battle::factory()->create(['title' => 'Marvel vs DC']);

        Livewire::test(SearchOverlay::class)
            ->set('query', 'ronaldo')
            ->assertViewHas('results', fn ($r) => $r->count() === 1 && $r->first()->is($match));
    }

    public function test_matches_side_labels(): void
    {
        $match = Battle::factory()->create([
            'title' => 'Clash',
            'side_a_label' => 'Ronaldo',
            'side_b_label' => 'Messi',
        ]);

        Livewire::test(SearchOverlay::class)
            ->set('query', 'ronald')
            ->assertViewHas('results', fn ($r) => $r->count() === 1 && $r->first()->is($match));
    }

    public function test_active_battles_rank_before_settled_on_same_match(): void
    {
        $active = Battle::factory()->create(['title' => 'Cats vs Dogs', 'total_pool' => 10]);
        $settled = Battle::factory()->settled()->create(['title' => 'Cats vs Birds', 'total_pool' => 9999]);

        Livewire::test(SearchOverlay::class)
            ->set('query', 'cats')
            ->assertViewHas('results', function ($r) use ($active, $settled) {
                return $r->count() === 2 && $r->first()->is($active) && $r->last()->is($settled);
            });
    }

    public function test_results_are_capped_at_15(): void
    {
        Battle::factory()->count(20)->create(['title' => 'abc battle']);

        Livewire::test(SearchOverlay::class)
            ->set('query', 'abc')
            ->assertViewHas('results', fn ($r) => $r->count() === 15);
    }
}

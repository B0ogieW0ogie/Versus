<?php

namespace Tests\Feature\Challenges;

use App\Livewire\SearchOverlay;
use App\Models\Battle;
use App\Models\Challenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChallengeSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_finds_people_and_public_challenges_but_not_battles(): void
    {
        config(['versus.battles_enabled' => false]);
        $maya = User::factory()->create(['username' => 'maya_fit', 'name' => 'Maya']);
        $match = Challenge::factory()->create(['title' => 'Fit pushups']);
        Challenge::factory()->processing()->create(['title' => 'Fit hidden']);
        Battle::factory()->create(['title' => 'Fit battle']);

        Livewire::test(SearchOverlay::class)
            ->set('query', 'fit')
            ->assertViewHas('people', fn ($p) => $p->count() === 1 && $p->first()->is($maya))
            ->assertViewHas('challenges', fn ($c) => $c->count() === 1 && $c->first()->is($match))
            ->assertViewHas('results', fn ($r) => $r->isEmpty())
            ->assertDontSee('Fit battle');
    }
}

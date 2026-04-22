<?php

namespace Tests\Feature\Livewire;

use App\Livewire\SidebarWidgets;
use App\Models\Battle;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SidebarWidgetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_players_ranked_by_winnings_not_balance(): void
    {
        [$rich, $winner] = User::factory()->count(2)->create()->all();
        $rich->update(['balance' => 100000]);
        $winner->update(['balance' => 100]);

        $battle = Battle::factory()->settled(Battle::SIDE_A)->create();
        Vote::factory()->create([
            'user_id' => $winner->id, 'battle_id' => $battle->id,
            'side' => 'A', 'amount' => 50, 'weight' => 50, 'payout' => 750,
        ]);

        Livewire::test(SidebarWidgets::class)
            ->assertViewHas('topPlayers', function ($players) use ($winner, $rich) {
                return $players->first()->id === $winner->id
                    && (float) $players->first()->total_winnings === 750.0
                    && (float) $players->firstWhere('id', $rich->id)?->total_winnings === 0.0;
            });
    }
}

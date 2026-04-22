<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Leaderboard;
use App\Models\Battle;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranks_users_by_sum_of_winning_payouts(): void
    {
        [$alice, $bob, $carol] = User::factory()->count(3)->create()->all();

        $battle = Battle::factory()->settled(Battle::SIDE_A)->create();

        Vote::factory()->create([
            'user_id' => $alice->id, 'battle_id' => $battle->id,
            'side' => 'A', 'amount' => 100, 'weight' => 100, 'payout' => 500,
        ]);
        Vote::factory()->create([
            'user_id' => $bob->id, 'battle_id' => $battle->id,
            'side' => 'A', 'amount' => 50, 'weight' => 50, 'payout' => 250,
        ]);
        Vote::factory()->create([
            'user_id' => $carol->id, 'battle_id' => $battle->id,
            'side' => 'B', 'amount' => 80, 'weight' => 80, 'payout' => null,
        ]);

        Livewire::test(Leaderboard::class)
            ->assertViewHas('rows', function ($rows) use ($alice, $bob, $carol) {
                $map = $rows->keyBy('id');

                return (float) $map[$alice->id]->total_winnings === 500.0
                    && (float) $map[$bob->id]->total_winnings === 250.0
                    && (float) $map[$carol->id]->total_winnings === 0.0;
            });
    }

    public function test_refund_votes_do_not_count_as_winnings(): void
    {
        $user = User::factory()->create();

        $tiedBattle = Battle::factory()->create([
            'status' => Battle::STATUS_SETTLED,
            'winning_side' => null,
            'settled_at' => now(),
        ]);

        Vote::factory()->create([
            'user_id' => $user->id, 'battle_id' => $tiedBattle->id,
            'side' => 'A', 'amount' => 100, 'weight' => 100, 'payout' => 100,
        ]);

        Livewire::test(Leaderboard::class)
            ->assertViewHas('rows', function ($rows) use ($user) {
                return (float) $rows->firstWhere('id', $user->id)->total_winnings === 0.0;
            });
    }

    public function test_ties_break_on_user_id(): void
    {
        $battle = Battle::factory()->settled(Battle::SIDE_A)->create();

        foreach (range(1, 3) as $_) {
            $u = User::factory()->create();
            Vote::factory()->create([
                'user_id' => $u->id, 'battle_id' => $battle->id,
                'side' => 'A', 'amount' => 10, 'weight' => 10, 'payout' => 10,
            ]);
        }

        $component = Livewire::test(Leaderboard::class);
        $ids = $component->viewData('rows')->pluck('id')->all();
        sort($ids);
        $this->assertSame($ids, $component->viewData('rows')->pluck('id')->all());
    }

    public function test_authed_user_outside_top_100_gets_your_position_row(): void
    {
        $battle = Battle::factory()->settled(Battle::SIDE_A)->create();

        // 100 users ahead
        for ($i = 0; $i < 100; $i++) {
            $u = User::factory()->create();
            Vote::factory()->create([
                'user_id' => $u->id, 'battle_id' => $battle->id,
                'side' => 'A', 'amount' => 10, 'weight' => 10, 'payout' => 1000,
            ]);
        }

        $me = User::factory()->create();
        Vote::factory()->create([
            'user_id' => $me->id, 'battle_id' => $battle->id,
            'side' => 'A', 'amount' => 1, 'weight' => 1, 'payout' => 1,
        ]);

        Livewire::actingAs($me)
            ->test(Leaderboard::class)
            ->assertViewHas('me', fn ($me) => $me !== null && $me['rank'] === 101 && (float) $me['total_winnings'] === 1.0);
    }

    public function test_guest_sees_no_your_position_row(): void
    {
        Livewire::test(Leaderboard::class)->assertViewHas('me', null);
    }
}

<?php

namespace Tests\Feature;

use App\Actions\Battles\CastVoteAction;
use App\Actions\Battles\SettleBattleAction;
use App\Models\Battle;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettlementTest extends TestCase
{
    use RefreshDatabase;

    private function vote(): CastVoteAction
    {
        return app(CastVoteAction::class);
    }

    private function settle(): SettleBattleAction
    {
        return app(SettleBattleAction::class);
    }

    private function closeAndSettle(Battle $battle): Battle
    {
        $battle->status = Battle::STATUS_CLOSED;
        $battle->closes_at = now()->subMinute();
        $battle->save();

        return ($this->settle())($battle);
    }

    public function test_winners_share_88_percent_proportionally(): void
    {
        $a1 = User::factory()->create(['balance' => 1000]);
        $a2 = User::factory()->create(['balance' => 1000]);
        $b1 = User::factory()->create(['balance' => 1000]);

        $battle = Battle::factory()->create();

        ($this->vote())($a1, $battle, Battle::SIDE_A, 100);
        ($this->vote())($a2, $battle, Battle::SIDE_A, 300);
        ($this->vote())($b1, $battle, Battle::SIDE_B, 100);

        $this->closeAndSettle($battle);

        $battle->refresh();
        $this->assertSame(Battle::STATUS_SETTLED, $battle->status);
        $this->assertSame('A', $battle->winning_side);
        $this->assertSame(500.0, (float) $battle->total_pool);

        // winners get 88% of 500 = 440, split 100:300 → 110 : 330
        $this->assertSame(900.0 + 110.0, (float) $a1->fresh()->balance);
        $this->assertSame(700.0 + 330.0, (float) $a2->fresh()->balance);
        $this->assertSame(900.0, (float) $b1->fresh()->balance);
    }

    public function test_project_burn_and_reward_pool_receive_correct_shares(): void
    {
        $a = User::factory()->create(['balance' => 1000]);
        $b = User::factory()->create(['balance' => 1000]);

        $battle = Battle::factory()->create();

        ($this->vote())($a, $battle, Battle::SIDE_A, 300);
        ($this->vote())($b, $battle, Battle::SIDE_B, 200);

        $this->closeAndSettle($battle);

        $this->assertDatabaseHas('transactions', [
            'user_id' => null,
            'type' => Transaction::TYPE_PROJECT_FEE,
            'amount' => '25.00',
            'battle_id' => $battle->id,
        ]);
        $this->assertDatabaseHas('transactions', [
            'user_id' => null,
            'type' => Transaction::TYPE_BURN,
            'amount' => '15.00',
            'battle_id' => $battle->id,
        ]);
        $this->assertDatabaseHas('transactions', [
            'user_id' => null,
            'type' => Transaction::TYPE_REWARD_POOL_CREDIT,
            'amount' => '20.00',
            'battle_id' => $battle->id,
        ]);
    }

    public function test_losers_get_no_payout(): void
    {
        $a = User::factory()->create(['balance' => 1000]);
        $b = User::factory()->create(['balance' => 1000]);

        $battle = Battle::factory()->create();

        ($this->vote())($a, $battle, Battle::SIDE_A, 300);
        ($this->vote())($b, $battle, Battle::SIDE_B, 100);

        $this->closeAndSettle($battle);

        // B lost their 100 stake entirely
        $this->assertSame(900.0, (float) $b->fresh()->balance);
        $this->assertDatabaseMissing('transactions', [
            'user_id' => $b->id,
            'type' => Transaction::TYPE_VOTE_PAYOUT,
        ]);
    }

    public function test_tie_refunds_all_stakes(): void
    {
        $a = User::factory()->create(['balance' => 1000]);
        $b = User::factory()->create(['balance' => 1000]);

        $battle = Battle::factory()->create();

        ($this->vote())($a, $battle, Battle::SIDE_A, 100);
        ($this->vote())($b, $battle, Battle::SIDE_B, 100);

        $settled = $this->closeAndSettle($battle);

        $this->assertNull($settled->winning_side);
        $this->assertSame(1000.0, (float) $a->fresh()->balance);
        $this->assertSame(1000.0, (float) $b->fresh()->balance);
    }

    public function test_battle_with_no_votes_settles_with_zero_pool(): void
    {
        $battle = Battle::factory()->create();

        $settled = $this->closeAndSettle($battle);

        $this->assertSame(Battle::STATUS_SETTLED, $settled->status);
        $this->assertSame(0.0, (float) $settled->total_pool);
        $this->assertSame(0, Transaction::count());
    }

    public function test_settle_due_command_closes_and_settles_expired_battles(): void
    {
        $a = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create(['closes_at' => now()->subMinute()]);

        // cast vote before closes_at expires using the factory default (status=active, closes_at future)
        $battle->closes_at = now()->addMinute();
        $battle->save();
        ($this->vote())($a, $battle, Battle::SIDE_A, 100);

        // now expire
        $battle->closes_at = now()->subMinute();
        $battle->save();

        $this->artisan('battles:settle-due')->assertSuccessful();

        $battle->refresh();
        $this->assertSame(Battle::STATUS_SETTLED, $battle->status);
        $this->assertSame('A', $battle->winning_side);
    }
}

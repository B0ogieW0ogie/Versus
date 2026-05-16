<?php

namespace Tests\Feature\Battles;

use App\Actions\Battles\AddBattlePoolAction;
use App\Actions\Battles\CastVoteAction;
use App\Actions\Battles\SettleBattleAction;
use App\Models\Battle;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AddBattlePoolTest extends TestCase
{
    use RefreshDatabase;

    public function test_adds_to_total_pool_and_writes_system_transaction(): void
    {
        $battle = Battle::factory()->create(['total_pool' => 100]);

        $updated = app(AddBattlePoolAction::class)($battle, 2500, 'Sponsor boost');

        $this->assertSame(2600.0, (float) $updated->total_pool);
        $this->assertDatabaseHas('transactions', [
            'user_id' => null,
            'battle_id' => $battle->id,
            'type' => Transaction::TYPE_BATTLE_POOL_CREDIT,
            'amount' => '2500.00',
        ]);
        $this->assertSame(
            'Sponsor boost',
            Transaction::query()
                ->where('battle_id', $battle->id)
                ->where('type', Transaction::TYPE_BATTLE_POOL_CREDIT)
                ->value('meta')['note'] ?? null,
        );
    }

    public function test_cannot_add_to_settled_battle(): void
    {
        $battle = Battle::factory()->settled()->create(['total_pool' => 500]);

        $this->expectException(RuntimeException::class);

        app(AddBattlePoolAction::class)($battle, 100);
    }

    public function test_settlement_includes_admin_pool_boost_in_winner_payouts(): void
    {
        $winner = User::factory()->create(['balance' => 1000]);
        $loser = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();

        app(CastVoteAction::class)($winner, $battle, Battle::SIDE_A, 200);
        app(CastVoteAction::class)($loser, $battle, Battle::SIDE_B, 100);
        app(AddBattlePoolAction::class)($battle->fresh(), 300);

        $battle->status = Battle::STATUS_CLOSED;
        $battle->closes_at = now()->subMinute();
        $battle->save();

        app(SettleBattleAction::class)($battle->fresh());

        // pool = 300 stakes + 300 boost = 600; winners share 88% = 528 to side A only
        $this->assertSame(1328.0, (float) $winner->fresh()->balance);
        $this->assertSame(900.0, (float) $loser->fresh()->balance);
        $this->assertSame(600.0, (float) $battle->fresh()->total_pool);
    }
}

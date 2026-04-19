<?php

namespace Tests\Feature;

use App\Actions\Battles\CastVoteAction;
use App\Actions\Battles\SettleBattleAction;
use App\Models\Battle;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralPayoutTest extends TestCase
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

    private function settleNow(Battle $battle): Battle
    {
        $battle->status = Battle::STATUS_CLOSED;
        $battle->closes_at = now()->subMinute();
        $battle->save();

        return ($this->settle())($battle);
    }

    public function test_referrer_gets_cut_when_referee_wins(): void
    {
        $referrer = User::factory()->create(['balance' => 1000]);
        $referee = User::factory()->create([
            'balance' => 1000,
            'referred_by_id' => $referrer->id,
        ]);
        $loser = User::factory()->create(['balance' => 1000]);

        $battle = Battle::factory()->create();

        // referrer also participates but on the losing side to isolate referral logic
        ($this->vote())($referrer, $battle, Battle::SIDE_B, 100);
        ($this->vote())($loser, $battle, Battle::SIDE_B, 100);
        ($this->vote())($referee, $battle, Battle::SIDE_A, 500);

        $this->settleNow($battle);

        // pool = 700; winners share = 616; referee is sole winner → payout = 616
        // reward_pool_credit = 28; referral_cut = 61.6 → capped at 28
        $referee->refresh();
        $referrer->refresh();

        $this->assertSame(500.0 + 616.0, (float) $referee->balance);

        // referrer had 900 balance after their losing 100 stake; gets 28 referral reward
        $this->assertSame(900.0 + 28.0, (float) $referrer->balance);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $referrer->id,
            'type' => Transaction::TYPE_REFERRAL_REWARD,
            'amount' => '28.00',
            'battle_id' => $battle->id,
        ]);
    }

    public function test_referrer_gets_nothing_when_referee_loses(): void
    {
        $referrer = User::factory()->create(['balance' => 1000]);
        $referee = User::factory()->create([
            'balance' => 1000,
            'referred_by_id' => $referrer->id,
        ]);
        $winner = User::factory()->create(['balance' => 1000]);

        $battle = Battle::factory()->create();

        ($this->vote())($winner, $battle, Battle::SIDE_A, 300);
        ($this->vote())($referee, $battle, Battle::SIDE_B, 100);

        $this->settleNow($battle);

        $this->assertDatabaseMissing('transactions', [
            'user_id' => $referrer->id,
            'type' => Transaction::TYPE_REFERRAL_REWARD,
        ]);
        $this->assertSame(1000.0, (float) $referrer->fresh()->balance);
    }

    public function test_referral_reward_never_exceeds_reward_pool_balance(): void
    {
        $referrer = User::factory()->create(['balance' => 1000]);
        $referee = User::factory()->create([
            'balance' => 10000,
            'referred_by_id' => $referrer->id,
        ]);

        $battle = Battle::factory()->create();

        // Only referee votes — they become the sole winner. Full pool flows to referee.
        ($this->vote())($referee, $battle, Battle::SIDE_A, 10000);

        $this->settleNow($battle);

        // pool = 10000; reward_pool_credit = 400; 10% cut would be 880 but capped at 400
        $rewardRow = Transaction::where('user_id', $referrer->id)
            ->where('type', Transaction::TYPE_REFERRAL_REWARD)
            ->firstOrFail();

        $this->assertSame(400.0, (float) $rewardRow->amount);

        // reward pool net balance must be >= 0
        $credit = (float) Transaction::whereNull('user_id')
            ->where('type', Transaction::TYPE_REWARD_POOL_CREDIT)
            ->sum('amount');
        $debit = (float) Transaction::whereNull('user_id')
            ->where('type', Transaction::TYPE_REWARD_POOL_DEBIT)
            ->sum('amount');
        $this->assertGreaterThanOrEqual(0.0, $credit + $debit);
    }

    public function test_self_referral_pays_no_reward(): void
    {
        // Edge case: winning voter is their own referrer (shouldn't be possible in signup,
        // but guard against it anyway)
        $user = User::factory()->create(['balance' => 1000]);
        $user->referred_by_id = $user->id;
        $user->save();

        $battle = Battle::factory()->create();
        ($this->vote())($user, $battle, Battle::SIDE_A, 500);

        $this->settleNow($battle);

        $this->assertDatabaseMissing('transactions', [
            'user_id' => $user->id,
            'type' => Transaction::TYPE_REFERRAL_REWARD,
        ]);
    }
}

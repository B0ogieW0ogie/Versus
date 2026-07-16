<?php

namespace Tests\Feature\Notifications;

use App\Actions\Battles\CastVoteAction;
use App\Actions\Battles\SettleBattleAction;
use App\Models\Battle;
use App\Models\User;
use App\Notifications\BattleSettled;
use App\Notifications\ReferralPayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SettlementNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function vote(): CastVoteAction
    {
        return app(CastVoteAction::class);
    }

    private function closeAndSettle(Battle $battle): Battle
    {
        $battle->status = Battle::STATUS_CLOSED;
        $battle->closes_at = now()->subMinute();
        $battle->save();

        return app(SettleBattleAction::class)($battle);
    }

    public function test_winners_and_losers_are_notified_with_result_and_amount(): void
    {
        Notification::fake();

        $winner = User::factory()->create(['balance' => 1000]);
        $loser = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();

        ($this->vote())($winner, $battle, Battle::SIDE_A, 300);
        ($this->vote())($loser, $battle, Battle::SIDE_B, 200);

        $this->closeAndSettle($battle);

        // pool 500, winners share 88% = 440, single winner takes it all
        Notification::assertSentTo($winner, BattleSettled::class, function (BattleSettled $n) use ($winner) {
            $data = $n->toDatabase($winner);

            return $data['result'] === BattleSettled::RESULT_WON && $data['amount'] === 440.0;
        });
        Notification::assertSentTo($loser, BattleSettled::class, function (BattleSettled $n) use ($loser) {
            $data = $n->toDatabase($loser);

            return $data['result'] === BattleSettled::RESULT_LOST && $data['amount'] === 0.0;
        });
    }

    public function test_multi_vote_winner_gets_one_notification_with_summed_payout(): void
    {
        Notification::fake();

        $winner = User::factory()->create(['balance' => 1000]);
        $loser = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();

        ($this->vote())($winner, $battle, Battle::SIDE_A, 100);
        ($this->vote())($winner, $battle, Battle::SIDE_A, 200);
        ($this->vote())($loser, $battle, Battle::SIDE_B, 200);

        $this->closeAndSettle($battle);

        Notification::assertSentToTimes($winner, BattleSettled::class, 1);
        Notification::assertSentTo($winner, BattleSettled::class, function (BattleSettled $n) use ($winner) {
            $data = $n->toDatabase($winner);

            return $data['result'] === BattleSettled::RESULT_WON && $data['amount'] === 440.0;
        });
    }

    public function test_stomp_refund_notifies_refunded_with_stake(): void
    {
        Notification::fake();

        $a = User::factory()->create(['balance' => 1000]);
        $b = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();

        // 950 / 1000 = 0.95 >= stomp_threshold 0.90 → void + refund
        ($this->vote())($a, $battle, Battle::SIDE_A, 950);
        ($this->vote())($b, $battle, Battle::SIDE_B, 50);

        $this->closeAndSettle($battle);

        Notification::assertSentTo($a, BattleSettled::class, function (BattleSettled $n) use ($a) {
            $data = $n->toDatabase($a);

            return $data['result'] === BattleSettled::RESULT_REFUNDED && $data['amount'] === 950.0;
        });
        Notification::assertSentTo($b, BattleSettled::class, function (BattleSettled $n) use ($b) {
            $data = $n->toDatabase($b);

            return $data['result'] === BattleSettled::RESULT_REFUNDED && $data['amount'] === 50.0;
        });
    }

    public function test_tie_goes_to_last_shot_and_sends_no_settlement_notifications(): void
    {
        Notification::fake();

        $a = User::factory()->create(['balance' => 1000]);
        $b = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();

        ($this->vote())($a, $battle, Battle::SIDE_A, 100);
        ($this->vote())($b, $battle, Battle::SIDE_B, 100);

        $this->closeAndSettle($battle);

        $this->assertSame(Battle::STATUS_LAST_SHOT, $battle->fresh()->status);

        // A tie pays nobody yet — the LAST SHOT call-to-arms is the only thing
        // that goes out (see LastShotNotificationsTest).
        Notification::assertNotSentTo([$a, $b], BattleSettled::class);
        Notification::assertNotSentTo([$a, $b], ReferralPayout::class);
    }

    public function test_referrer_is_notified_about_referral_payout(): void
    {
        Notification::fake();

        $referrer = User::factory()->create(['balance' => 1000]);
        $referee = User::factory()->create(['balance' => 1000, 'referred_by_id' => $referrer->id]);
        $loser = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();

        ($this->vote())($referee, $battle, Battle::SIDE_A, 300);
        ($this->vote())($loser, $battle, Battle::SIDE_B, 200);

        $this->closeAndSettle($battle);

        // referee payout 440 → referral cut 10% = 44, capped by reward pool (4% of 500 = 20)
        Notification::assertSentTo($referrer, ReferralPayout::class, function (ReferralPayout $n) use ($referrer, $referee) {
            $data = $n->toDatabase($referrer);

            return $data['referee_name'] === $referee->name && $data['amount'] === 20.0;
        });
    }
}

<?php

namespace Tests\Feature\Notifications;

use App\Actions\Battles\CastVoteAction;
use App\Actions\Battles\SettleBattleAction;
use App\Models\Battle;
use App\Models\User;
use App\Notifications\BattleLastShot;
use App\Notifications\BattleSettled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LastShotNotificationsTest extends TestCase
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

    public function test_tie_notifies_every_staker_on_both_sides(): void
    {
        Notification::fake();

        $a = User::factory()->create(['balance' => 1000]);
        $b = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();

        ($this->vote())($a, $battle, Battle::SIDE_A, 100);
        ($this->vote())($b, $battle, Battle::SIDE_B, 100);

        $this->closeAndSettle($battle);

        $this->assertSame(Battle::STATUS_LAST_SHOT, $battle->fresh()->status);

        foreach ([$a, $b] as $staker) {
            Notification::assertSentTo($staker, BattleLastShot::class, function (BattleLastShot $n) use ($staker, $battle) {
                $data = $n->toDatabase($staker);

                return $data['battle_id'] === $battle->id
                    && $data['battle_slug'] === $battle->slug
                    && $data['battle_title'] === $battle->title;
            });
        }
    }

    public function test_multi_vote_staker_is_notified_once(): void
    {
        Notification::fake();

        $a = User::factory()->create(['balance' => 1000]);
        $b = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();

        ($this->vote())($a, $battle, Battle::SIDE_A, 60);
        ($this->vote())($a, $battle, Battle::SIDE_A, 40);
        ($this->vote())($b, $battle, Battle::SIDE_B, 100);

        $this->closeAndSettle($battle);

        Notification::assertSentToTimes($a, BattleLastShot::class, 1);
    }

    public function test_non_stakers_are_not_notified(): void
    {
        Notification::fake();

        $a = User::factory()->create(['balance' => 1000]);
        $b = User::factory()->create(['balance' => 1000]);
        $bystander = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();

        ($this->vote())($a, $battle, Battle::SIDE_A, 100);
        ($this->vote())($b, $battle, Battle::SIDE_B, 100);

        $this->closeAndSettle($battle);

        Notification::assertNotSentTo($bystander, BattleLastShot::class);
    }

    public function test_normal_settlement_sends_no_last_shot_notification(): void
    {
        Notification::fake();

        $winner = User::factory()->create(['balance' => 1000]);
        $loser = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();

        ($this->vote())($winner, $battle, Battle::SIDE_A, 300);
        ($this->vote())($loser, $battle, Battle::SIDE_B, 200);

        $this->closeAndSettle($battle);

        $this->assertSame(Battle::STATUS_SETTLED, $battle->fresh()->status);
        Notification::assertNotSentTo($winner, BattleLastShot::class);
        Notification::assertNotSentTo($loser, BattleLastShot::class);
    }

    public function test_tie_break_vote_settles_and_sends_settled_not_last_shot(): void
    {
        $a = User::factory()->create(['balance' => 1000]);
        $b = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();

        ($this->vote())($a, $battle, Battle::SIDE_A, 100);
        ($this->vote())($b, $battle, Battle::SIDE_B, 100);

        $this->closeAndSettle($battle);
        $this->assertSame(Battle::STATUS_LAST_SHOT, $battle->fresh()->status);

        Notification::fake();

        // the tie-break stake reopens nothing: it settles the battle immediately
        ($this->vote())($a, $battle->fresh(), Battle::SIDE_A, 50);

        $this->assertSame(Battle::STATUS_SETTLED, $battle->fresh()->status);
        Notification::assertNotSentTo($a, BattleLastShot::class);
        Notification::assertSentTo($a, BattleSettled::class);
    }
}

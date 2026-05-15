<?php

namespace Tests\Feature;

use App\Actions\Battles\CastVoteAction;
use App\Models\Battle;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VoteTest extends TestCase
{
    use RefreshDatabase;

    private function action(): CastVoteAction
    {
        return app(CastVoteAction::class);
    }

    public function test_vote_deducts_balance_creates_vote_and_transaction(): void
    {
        $user = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();

        $vote = ($this->action())($user, $battle, Battle::SIDE_A, 200);

        $this->assertSame(800.0, (float) $user->fresh()->balance);
        $this->assertSame(200.0, (float) $battle->fresh()->total_pool);
        $this->assertSame('A', $vote->side);
        $this->assertSame(200.0, (float) $vote->weight);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'battle_id' => $battle->id,
            'type' => Transaction::TYPE_VOTE_STAKE,
            'amount' => '-200.00',
        ]);
    }

    public function test_vote_copies_referrer_from_user(): void
    {
        $referrer = User::factory()->create();
        $user = User::factory()->create(['balance' => 500, 'referred_by_id' => $referrer->id]);
        $battle = Battle::factory()->create();

        $vote = ($this->action())($user, $battle, Battle::SIDE_B, 100);

        $this->assertSame($referrer->id, $vote->referrer_id);
    }

    public function test_user_can_vote_multiple_times_in_same_battle_on_either_side(): void
    {
        $user = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();

        ($this->action())($user, $battle, Battle::SIDE_A, 100);
        ($this->action())($user, $battle, Battle::SIDE_B, 50);
        ($this->action())($user, $battle, Battle::SIDE_A, 25);

        $this->assertSame(3, Vote::where('user_id', $user->id)->where('battle_id', $battle->id)->count());
        $this->assertSame(825.0, (float) $user->fresh()->balance);
        $this->assertSame(175.0, (float) $battle->fresh()->total_pool);
    }

    public function test_user_cannot_vote_more_than_balance(): void
    {
        $user = User::factory()->create(['balance' => 50]);
        $battle = Battle::factory()->create();

        $this->expectException(ValidationException::class);

        try {
            ($this->action())($user, $battle, Battle::SIDE_A, 100);
        } finally {
            $this->assertSame(0, Vote::count());
            $this->assertSame(50.0, (float) $user->fresh()->balance);
        }
    }

    public function test_cannot_vote_on_closed_battle(): void
    {
        $user = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->closed()->create();

        $this->expectException(ValidationException::class);
        ($this->action())($user, $battle, Battle::SIDE_A, 100);
    }

    public function test_cannot_vote_after_closes_at(): void
    {
        $user = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create([
            'status' => Battle::STATUS_ACTIVE,
            'closes_at' => now()->subMinute(),
        ]);

        $this->expectException(ValidationException::class);
        ($this->action())($user, $battle, Battle::SIDE_A, 100);
    }

    public function test_invalid_side_is_rejected(): void
    {
        $user = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();

        $this->expectException(ValidationException::class);
        ($this->action())($user, $battle, 'C', 100);
    }

    public function test_non_positive_amount_is_rejected(): void
    {
        $user = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();

        $this->expectException(ValidationException::class);
        ($this->action())($user, $battle, Battle::SIDE_A, 0);
    }

    public function test_single_vote_cannot_exceed_max_vote_amount(): void
    {
        config()->set('versus.max_vote_amount', 10000);

        $user = User::factory()->create(['balance' => 20000]);
        $battle = Battle::factory()->create();

        try {
            ($this->action())($user, $battle, Battle::SIDE_A, 10001);
            $this->fail('Expected ValidationException for exceeding per-vote cap.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        $this->assertSame(0, Vote::where('user_id', $user->id)->count());
        $this->assertSame(20000.0, (float) $user->fresh()->balance);
    }

    public function test_single_vote_can_equal_max_vote_amount(): void
    {
        config()->set('versus.max_vote_amount', 10000);

        $user = User::factory()->create(['balance' => 10000]);
        $battle = Battle::factory()->create();

        ($this->action())($user, $battle, Battle::SIDE_A, 10000);

        $this->assertSame(10000.0, (float) Vote::where('user_id', $user->id)->sum('amount'));
    }

    public function test_user_can_stake_past_cap_in_sum_across_battles(): void
    {
        config()->set('versus.max_vote_amount', 10000);

        $user = User::factory()->create(['balance' => 30000]);
        $battleA = Battle::factory()->create();
        $battleB = Battle::factory()->create();

        ($this->action())($user, $battleA, Battle::SIDE_A, 10000);
        ($this->action())($user, $battleB, Battle::SIDE_B, 10000);

        $this->assertSame(20000.0, (float) Vote::where('user_id', $user->id)->sum('amount'));
        $this->assertSame(10000.0, (float) $user->fresh()->balance);
    }

    public function test_user_can_stake_up_to_battle_cap_across_multiple_votes_and_sides(): void
    {
        config()->set('versus.max_battle_stake_per_user', 30000);

        $user = User::factory()->create(['balance' => 50000]);
        $battle = Battle::factory()->create();

        ($this->action())($user, $battle, Battle::SIDE_A, 10000);
        ($this->action())($user, $battle, Battle::SIDE_B, 10000);
        ($this->action())($user, $battle, Battle::SIDE_A, 10000);

        $this->assertSame(30000.0, (float) Vote::query()
            ->where('user_id', $user->id)
            ->where('battle_id', $battle->id)
            ->sum('amount'));
    }

    public function test_user_cannot_exceed_battle_stake_cap_in_same_battle(): void
    {
        config()->set('versus.max_battle_stake_per_user', 30000);

        $user = User::factory()->create(['balance' => 50000]);
        $battle = Battle::factory()->create();

        ($this->action())($user, $battle, Battle::SIDE_A, 10000);
        ($this->action())($user, $battle, Battle::SIDE_B, 10000);
        ($this->action())($user, $battle, Battle::SIDE_A, 9000);

        try {
            ($this->action())($user, $battle, Battle::SIDE_B, 2000);
            $this->fail('Expected ValidationException for exceeding per-battle stake cap.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        $this->assertSame(29000.0, (float) Vote::query()
            ->where('user_id', $user->id)
            ->where('battle_id', $battle->id)
            ->sum('amount'));
    }

    public function test_user_can_stake_battle_cap_in_each_of_two_battles(): void
    {
        config()->set('versus.max_battle_stake_per_user', 30000);
        config()->set('versus.max_vote_amount', 30000);

        $user = User::factory()->create(['balance' => 70000]);
        $battleA = Battle::factory()->create();
        $battleB = Battle::factory()->create();

        ($this->action())($user, $battleA, Battle::SIDE_A, 30000);
        ($this->action())($user, $battleB, Battle::SIDE_B, 30000);

        $this->assertSame(30000.0, (float) Vote::query()->where('user_id', $user->id)->where('battle_id', $battleA->id)->sum('amount'));
        $this->assertSame(30000.0, (float) Vote::query()->where('user_id', $user->id)->where('battle_id', $battleB->id)->sum('amount'));
    }
}

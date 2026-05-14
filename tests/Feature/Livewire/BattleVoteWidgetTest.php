<?php

namespace Tests\Feature\Livewire;

use App\Livewire\BattleVoteWidget;
use App\Models\Battle;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BattleVoteWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_sign_in_link_not_form(): void
    {
        $battle = Battle::factory()->create();

        Livewire::test(BattleVoteWidget::class, ['battle' => $battle])
            ->assertSee(__('battle.sign_in_to_vote'))
            ->assertDontSee(__('battle.widget_submit_vote'));
    }

    public function test_authed_user_sees_form_with_pool_stats(): void
    {
        $user = User::factory()->create(['balance' => 500]);
        $other = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();

        Vote::factory()->create(['user_id' => $other->id, 'battle_id' => $battle->id, 'side' => 'A', 'amount' => 300, 'weight' => 300]);
        Vote::factory()->create(['user_id' => $other->id, 'battle_id' => $battle->id, 'side' => 'B', 'amount' => 100, 'weight' => 100]);

        Livewire::actingAs($user)
            ->test(BattleVoteWidget::class, ['battle' => $battle])
            ->assertViewHas('poolA', 300.0)
            ->assertViewHas('poolB', 100.0)
            ->assertViewHas('maxAllowed', 500)
            ->assertViewHas('canVote', true)
            ->assertSee(__('battle.widget_chip_max'))
            ->assertSee(__('battle.widget_submit_vote'));
    }

    public function test_cast_vote_delegates_to_action_and_dispatches_events(): void
    {
        $user = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();

        Livewire::actingAs($user)
            ->test(BattleVoteWidget::class, ['battle' => $battle])
            ->call('castVote', 'A', 200)
            ->assertHasNoErrors('vote')
            ->assertDispatched('battle-voted')
            ->assertDispatched('balance-updated');

        $this->assertSame(800.0, (float) $user->fresh()->balance);
        $this->assertSame(200.0, (float) $battle->fresh()->total_pool);
        $this->assertDatabaseHas('votes', [
            'user_id' => $user->id,
            'battle_id' => $battle->id,
            'side' => 'A',
            'amount' => '200.00',
        ]);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'battle_id' => $battle->id,
            'type' => Transaction::TYPE_VOTE_STAKE,
            'amount' => '-200.00',
        ]);
    }

    public function test_invalid_side_surfaces_vote_error(): void
    {
        $user = User::factory()->create(['balance' => 500]);
        $battle = Battle::factory()->create();

        Livewire::actingAs($user)
            ->test(BattleVoteWidget::class, ['battle' => $battle])
            ->call('castVote', 'C', 100)
            ->assertHasErrors('vote');

        $this->assertSame(0, Vote::count());
    }

    public function test_closed_battle_hides_form(): void
    {
        $user = User::factory()->create(['balance' => 500]);
        $battle = Battle::factory()->closed()->create();

        Livewire::actingAs($user)
            ->test(BattleVoteWidget::class, ['battle' => $battle])
            ->assertViewHas('canVote', false)
            ->assertSee(__('battle.voting_closed'))
            ->assertDontSee(__('battle.widget_submit_vote'));
    }

    public function test_over_cap_amount_surfaces_vote_error(): void
    {
        config()->set('versus.max_vote_amount', 10000);

        $user = User::factory()->create(['balance' => 50000]);
        $battle = Battle::factory()->create();

        Livewire::actingAs($user)
            ->test(BattleVoteWidget::class, ['battle' => $battle])
            ->call('castVote', 'A', 10001)
            ->assertHasErrors('vote');

        $this->assertSame(0, Vote::count());
        $this->assertSame(50000.0, (float) $user->fresh()->balance);
    }

    public function test_default_side_matches_user_larger_stake(): void
    {
        $user = User::factory()->create(['balance' => 500]);
        $battle = Battle::factory()->create();

        Vote::factory()->create(['user_id' => $user->id, 'battle_id' => $battle->id, 'side' => 'B', 'amount' => 50, 'weight' => 50]);
        Vote::factory()->create(['user_id' => $user->id, 'battle_id' => $battle->id, 'side' => 'B', 'amount' => 25, 'weight' => 25]);
        Vote::factory()->create(['user_id' => $user->id, 'battle_id' => $battle->id, 'side' => 'A', 'amount' => 10, 'weight' => 10]);

        Livewire::actingAs($user)
            ->test(BattleVoteWidget::class, ['battle' => $battle])
            ->assertViewHas('defaultSide', Battle::SIDE_B);
    }
}

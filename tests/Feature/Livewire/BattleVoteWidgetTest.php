<?php

namespace Tests\Feature\Livewire;

use App\Actions\Battles\AddBattlePoolAction;
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
        $battle = Battle::factory()->create(['total_pool' => 400]);

        Vote::factory()->create(['user_id' => $other->id, 'battle_id' => $battle->id, 'side' => 'A', 'amount' => 300, 'weight' => 300]);
        Vote::factory()->create(['user_id' => $other->id, 'battle_id' => $battle->id, 'side' => 'B', 'amount' => 100, 'weight' => 100]);

        Livewire::actingAs($user)
            ->test(BattleVoteWidget::class, ['battle' => $battle])
            ->assertViewHas('totalPool', 400.0)
            ->assertViewHas('maxAllowed', 500)
            ->assertViewHas('canVote', true)
            ->assertSee(__('battle.widget_chip_max'))
            ->assertSee(__('battle.widget_submit_vote'));
    }

    public function test_total_pool_reflects_admin_boost(): void
    {
        $user = User::factory()->create(['balance' => 500]);
        $battle = Battle::factory()->create(['total_pool' => 0]);

        app(AddBattlePoolAction::class)($battle, 125_000);

        Livewire::actingAs($user)
            ->test(BattleVoteWidget::class, ['battle' => $battle->fresh()])
            ->assertViewHas('totalPool', 125_000.0);
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
            ->assertDispatched('balance-updated')
            ->assertDispatched(
                'versus-stake-toast',
                title: str_replace('__N__', '200', __('battle.stake_modal_title')),
                body: __('battle.stake_modal_body'),
                battleId: $battle->id,
            );

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

    public function test_cast_vote_dispatches_remaining_battle_stake_after_vote(): void
    {
        config()->set('versus.max_battle_stake_per_user', 30000);
        config()->set('versus.max_vote_amount', 30000);

        $user = User::factory()->create(['balance' => 100000]);
        $battle = Battle::factory()->create();

        Livewire::actingAs($user)
            ->test(BattleVoteWidget::class, ['battle' => $battle])
            ->call('castVote', 'A', 1000)
            ->assertDispatched(
                'versus-stake-toast',
                battleId: $battle->id,
                remainingBattleStake: 29000,
            );
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

    public function test_max_allowed_respects_remaining_battle_stake_cap(): void
    {
        config()->set('versus.max_battle_stake_per_user', 30000);
        config()->set('versus.max_vote_amount', 10000);

        $user = User::factory()->create(['balance' => 100000]);
        $battle = Battle::factory()->create();

        Vote::factory()->create([
            'user_id' => $user->id,
            'battle_id' => $battle->id,
            'side' => 'A',
            'amount' => 25000,
            'weight' => 25000,
        ]);

        Livewire::actingAs($user)
            ->test(BattleVoteWidget::class, ['battle' => $battle])
            ->assertViewHas('maxAllowed', 5000)
            ->assertViewHas('remainingBattleStake', 5000)
            ->assertViewHas('canVote', true);
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

<?php

namespace Tests\Feature\Livewire;

use App\Livewire\MyBets;
use App\Models\Battle;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MyBetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_tab_filters_to_votes_on_active_battles(): void
    {
        $user = User::factory()->create();
        $active = Battle::factory()->create();
        $settled = Battle::factory()->settled()->create();

        Vote::factory()->create(['user_id' => $user->id, 'battle_id' => $active->id, 'side' => 'A', 'amount' => 10, 'weight' => 10]);
        Vote::factory()->create(['user_id' => $user->id, 'battle_id' => $settled->id, 'side' => 'A', 'amount' => 20, 'weight' => 20, 'payout' => 100]);

        Livewire::actingAs($user)
            ->test(MyBets::class)
            ->assertViewHas('votes', fn ($v) => $v->count() === 1 && $v->first()->battle_id === $active->id);
    }

    public function test_settled_tab_won_status_and_payout(): void
    {
        $user = User::factory()->create();
        $battle = Battle::factory()->settled(Battle::SIDE_A)->create();
        $vote = Vote::factory()->create([
            'user_id' => $user->id, 'battle_id' => $battle->id,
            'side' => 'A', 'amount' => 50, 'weight' => 50, 'payout' => 250,
        ]);

        $component = Livewire::actingAs($user)->test(MyBets::class)->set('tab', 'settled');
        $this->assertSame('won', $component->instance()->statusFor($vote));
        $this->assertSame(250.0, $component->instance()->netAmountFor($vote));
    }

    public function test_settled_tab_lost_status_and_negative_net(): void
    {
        $user = User::factory()->create();
        $battle = Battle::factory()->settled(Battle::SIDE_A)->create();
        $vote = Vote::factory()->create([
            'user_id' => $user->id, 'battle_id' => $battle->id,
            'side' => 'B', 'amount' => 50, 'weight' => 50, 'payout' => null,
        ]);

        $component = Livewire::actingAs($user)->test(MyBets::class)->set('tab', 'settled');
        $this->assertSame('lost', $component->instance()->statusFor($vote));
        $this->assertSame(-50.0, $component->instance()->netAmountFor($vote));
    }

    public function test_settled_tab_refund_status_on_tie(): void
    {
        $user = User::factory()->create();
        $tied = Battle::factory()->create([
            'status' => Battle::STATUS_SETTLED,
            'winning_side' => null,
            'settled_at' => now(),
        ]);
        $vote = Vote::factory()->create([
            'user_id' => $user->id, 'battle_id' => $tied->id,
            'side' => 'A', 'amount' => 75, 'weight' => 75, 'payout' => 75,
        ]);

        $component = Livewire::actingAs($user)->test(MyBets::class)->set('tab', 'settled');
        $this->assertSame('refund', $component->instance()->statusFor($vote));
        $this->assertSame(75.0, $component->instance()->netAmountFor($vote));
    }

    public function test_multiple_votes_on_same_battle_produce_multiple_rows(): void
    {
        $user = User::factory()->create();
        $battle = Battle::factory()->create();

        Vote::factory()->count(3)->create([
            'user_id' => $user->id, 'battle_id' => $battle->id,
            'side' => 'A', 'amount' => 10, 'weight' => 10,
        ]);

        Livewire::actingAs($user)
            ->test(MyBets::class)
            ->assertViewHas('votes', fn ($v) => $v->count() === 3);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/my-bets')->assertRedirect('/login');
    }
}

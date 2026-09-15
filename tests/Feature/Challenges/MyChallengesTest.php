<?php

namespace Tests\Feature\Challenges;

use App\Livewire\MyChallenges;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeParticipant;
use App\Models\User;
use App\Services\Challenges\MyChallengesQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MyChallengesTest extends TestCase
{
    use RefreshDatabase;

    private function accept(User $user, Challenge $challenge): void
    {
        ChallengeParticipant::create(['user_id' => $user->id, 'challenge_id' => $challenge->id, 'accepted_at' => now()]);
    }

    public function test_lists_own_and_accepted_in_status_order_with_states(): void
    {
        $user = User::factory()->create();

        $closed = Challenge::factory()->closed()->withOriginal()->create();
        $this->accept($user, $closed);
        $ownFailed = Challenge::factory()->for($user)->failed()->create();
        $later = Challenge::factory()->withOriginal()->create(['ends_at' => now()->addDays(2)]);
        $this->accept($user, $later);
        $sooner = Challenge::factory()->withOriginal()->create(['ends_at' => now()->addHour()]);
        $this->accept($user, $sooner);
        ChallengeEntry::factory()->for($sooner)->for($user)->processing()->create();
        $own = Challenge::factory()->for($user)->withOriginal()->create(['ends_at' => now()->addDays(5)]);
        Challenge::factory()->withOriginal()->create(); // unrelated

        $cards = collect(app(MyChallengesQuery::class)->cards($user, 20));

        $this->assertSame(
            [$sooner->id, $later->id, $own->id, $ownFailed->id, $closed->id],
            $cards->pluck('challenge.id')->all(),
        );
        $this->assertSame(
            ['response_processing', 'accepted_open', 'own', 'own_failed', 'closed'],
            $cards->pluck('state')->all(),
        );
        $this->assertTrue($cards[0]['has_entry']);
        $this->assertSame(3, app(MyChallengesQuery::class)->activeCount($user));
    }

    public function test_page_renders_cards_buttons_and_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('challenges.mine'))->assertOk()->assertSee(__('challenges.my_empty'));

        $challenge = Challenge::factory()->withOriginal()->create(['title' => 'Skate Trick Challenge']);
        $this->accept($user, $challenge);

        $this->actingAs($user)->get(route('challenges.mine'))
            ->assertOk()
            ->assertSee('Skate Trick Challenge')
            ->assertSee(__('challenges.upload_response'))
            ->assertSee(route('challenges.respond', $challenge->slug), false);
    }

    public function test_closed_card_shows_winner_and_results_link(): void
    {
        $user = User::factory()->create();
        $challenge = Challenge::factory()->closed()->withOriginal()->create();
        $winner = ChallengeEntry::factory()->for($challenge)->for($user)->create();
        $this->accept($user, $challenge); // SubmitResponseAction always records participation.
        $challenge->update(['winner_entry_id' => $winner->id]);

        $this->actingAs($user)->get(route('challenges.mine'))
            ->assertSee(__('challenges.winner_short', ['name' => $user->name]))
            ->assertSee(route('challenges.show', ['challenge' => $challenge->slug, 'entry' => $winner->id]), false)
            ->assertDontSee(__('challenges.upload_response'));
    }

    public function test_leave_removes_accepted_challenge(): void
    {
        $user = User::factory()->create();
        $challenge = Challenge::factory()->withOriginal()->create();
        $this->accept($user, $challenge);

        Livewire::actingAs($user)->test(MyChallenges::class)->call('leave', $challenge->id);

        $this->assertSame(0, ChallengeParticipant::count());
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('challenges.mine'))->assertRedirect(route('login'));
    }
}

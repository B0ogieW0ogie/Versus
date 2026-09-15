<?php

namespace Tests\Feature\Challenges;

use App\Actions\Challenges\CastChallengeVoteAction;
use App\Actions\Challenges\CloseChallengeAction;
use App\Events\ChallengeClosed;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeParticipant;
use App\Models\User;
use App\Notifications\ChallengeEndingSoon;
use App\Notifications\ChallengeResults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CloseChallengeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function votes(ChallengeEntry $entry, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            app(CastChallengeVoteAction::class)(User::factory()->create(), $entry);
        }
    }

    private function expire(Challenge $challenge): Challenge
    {
        $challenge->update(['ends_at' => now()->subSecond()]);

        return $challenge->fresh();
    }

    public function test_most_voted_entry_wins_and_everyone_with_an_entry_is_notified(): void
    {
        Event::fake([ChallengeClosed::class]);
        $challenge = Challenge::factory()->withOriginal()->create();
        $a = ChallengeEntry::factory()->for($challenge)->create();
        $b = ChallengeEntry::factory()->for($challenge)->create();
        $this->votes($a, 1);
        $this->votes($b, 2);

        $closed = app(CloseChallengeAction::class)($this->expire($challenge));

        $this->assertSame(Challenge::STATUS_CLOSED, $closed->status);
        $this->assertSame($b->id, $closed->winner_entry_id);
        $this->assertSame(3, $closed->votes_count);
        $this->assertNotNull($closed->closed_at);
        Event::assertDispatched(ChallengeClosed::class);
        Notification::assertSentTo($b->user, ChallengeResults::class, fn ($n) => $n->toDatabase($b->user)['result'] === ChallengeResults::WON);
        Notification::assertSentTo($a->user, ChallengeResults::class, fn ($n) => $n->toDatabase($a->user)['result'] === ChallengeResults::OVER);
        Notification::assertSentTo($challenge->user, ChallengeResults::class);
    }

    public function test_tie_goes_to_the_earlier_submission_so_the_original_wins_ties(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $challenge->original->update(['submitted_at' => now()->subHour()]);
        $response = ChallengeEntry::factory()->for($challenge)->create(['submitted_at' => now()]);
        $this->votes($challenge->original, 1);
        $this->votes($response, 1);

        $closed = app(CloseChallengeAction::class)($this->expire($challenge));

        $this->assertSame($challenge->original->id, $closed->winner_entry_id);
    }

    public function test_zero_votes_means_no_winner(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        ChallengeEntry::factory()->for($challenge)->create();

        $closed = app(CloseChallengeAction::class)($this->expire($challenge));

        $this->assertNull($closed->winner_entry_id);
        Notification::assertSentTo($challenge->user, ChallengeResults::class, fn ($n) => $n->toDatabase($challenge->user)['result'] === ChallengeResults::NO_VOTES);
    }

    public function test_recount_ignores_stale_cached_counters(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $a = ChallengeEntry::factory()->for($challenge)->create(['votes_count' => 50]);
        $b = ChallengeEntry::factory()->for($challenge)->create();
        $this->votes($b, 1);

        $closed = app(CloseChallengeAction::class)($this->expire($challenge));

        $this->assertSame($b->id, $closed->winner_entry_id);
        $this->assertSame(0, $a->fresh()->votes_count);
    }

    public function test_not_yet_due_and_already_closed_are_noops(): void
    {
        Event::fake([ChallengeClosed::class]);
        $open = Challenge::factory()->withOriginal()->create();
        app(CloseChallengeAction::class)($open);
        $this->assertSame(Challenge::STATUS_ACTIVE, $open->fresh()->status);

        $closed = app(CloseChallengeAction::class)($this->expire($open));
        app(CloseChallengeAction::class)($closed);
        Event::assertDispatchedTimes(ChallengeClosed::class, 1);
    }

    public function test_command_closes_due_and_reminds_once(): void
    {
        $due = Challenge::factory()->withOriginal()->create(['ends_at' => now()->subMinute()]);
        $soon = Challenge::factory()->withOriginal()->create(['ends_at' => now()->addMinutes(30)]);
        $waiting = User::factory()->create();
        $answered = User::factory()->create();
        ChallengeParticipant::create(['user_id' => $waiting->id, 'challenge_id' => $soon->id, 'accepted_at' => now()]);
        ChallengeParticipant::create(['user_id' => $answered->id, 'challenge_id' => $soon->id, 'accepted_at' => now()]);
        ChallengeEntry::factory()->for($soon)->for($answered)->create();

        $this->artisan('challenges:close-due')->assertSuccessful();
        $this->artisan('challenges:close-due')->assertSuccessful();

        $this->assertSame(Challenge::STATUS_CLOSED, $due->fresh()->status);
        $this->assertSame(Challenge::STATUS_ACTIVE, $soon->fresh()->status);
        Notification::assertSentToTimes($waiting, ChallengeEndingSoon::class, 1);
        Notification::assertNotSentTo($answered, ChallengeEndingSoon::class);
    }
}

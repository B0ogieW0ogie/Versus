<?php

namespace Tests\Feature\Challenges;

use App\Actions\Challenges\CastChallengeVoteAction;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeVote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CastChallengeVoteActionTest extends TestCase
{
    use RefreshDatabase;

    private function vote(User $user, ChallengeEntry $entry): ChallengeVote
    {
        return app(CastChallengeVoteAction::class)($user, $entry);
    }

    public function test_first_vote_increments_entry_and_challenge(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $entry = ChallengeEntry::factory()->for($challenge)->create();
        $voter = User::factory()->create();

        $this->vote($voter, $entry);

        $this->assertSame(1, $entry->fresh()->votes_count);
        $this->assertSame(1, $challenge->fresh()->votes_count);
    }

    public function test_voting_for_the_original_is_allowed(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();

        $this->vote(User::factory()->create(), $challenge->original);

        $this->assertSame(1, $challenge->original->fresh()->votes_count);
    }

    public function test_moving_a_vote_shifts_counters_and_keeps_total(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $a = ChallengeEntry::factory()->for($challenge)->create();
        $b = ChallengeEntry::factory()->for($challenge)->create();
        $voter = User::factory()->create();

        $this->vote($voter, $a);
        $this->vote($voter, $b);

        $this->assertSame(0, $a->fresh()->votes_count);
        $this->assertSame(1, $b->fresh()->votes_count);
        $this->assertSame(1, $challenge->fresh()->votes_count);
        $this->assertSame(1, ChallengeVote::count());
    }

    public function test_repeat_vote_for_same_entry_is_a_noop(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $a = ChallengeEntry::factory()->for($challenge)->create();
        $voter = User::factory()->create();

        $this->vote($voter, $a);
        $this->vote($voter, $a);

        $this->assertSame(1, $a->fresh()->votes_count);
    }

    public function test_cannot_vote_for_own_entry(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();

        $this->expectException(ValidationException::class);
        $this->vote($challenge->user, $challenge->original);
    }

    public function test_cannot_vote_for_processing_entry(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $entry = ChallengeEntry::factory()->for($challenge)->processing()->create();

        $this->expectException(ValidationException::class);
        $this->vote(User::factory()->create(), $entry);
    }

    public function test_cannot_vote_after_deadline_even_before_close_command_runs(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create(['ends_at' => now()->subSecond()]);

        $this->expectException(ValidationException::class);
        $this->vote(User::factory()->create(), $challenge->original);
    }
}

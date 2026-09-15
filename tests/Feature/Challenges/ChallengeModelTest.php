<?php

namespace Tests\Feature\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeVote;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ChallengeModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_open_requires_active_status_and_future_deadline(): void
    {
        $this->assertTrue(Challenge::factory()->create()->isOpen());
        $this->assertFalse(Challenge::factory()->create(['ends_at' => now()->subSecond()])->isOpen());
        $this->assertFalse(Challenge::factory()->processing()->create()->isOpen());
        $this->assertFalse(Challenge::factory()->closed()->create()->isOpen());
    }

    public function test_duration_minutes_come_from_config(): void
    {
        $this->assertSame(1440, Challenge::durationMinutes(Challenge::DURATION_24H));
        $this->assertSame(10080, Challenge::durationMinutes(Challenge::DURATION_7D));

        $this->expectException(InvalidArgumentException::class);
        Challenge::durationMinutes('1y');
    }

    public function test_with_original_creates_a_ready_original_entry(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();

        $this->assertTrue($challenge->original->is_original);
        $this->assertSame($challenge->user_id, $challenge->original->user_id);
        $this->assertSame(ChallengeEntry::STATUS_READY, $challenge->original->status);
        $this->assertStringEndsWith('.mp4', (string) $challenge->original->videoUrl());
    }

    public function test_one_entry_per_user_per_challenge(): void
    {
        $challenge = Challenge::factory()->create();
        $user = User::factory()->create();
        ChallengeEntry::factory()->for($challenge)->for($user)->create();

        $this->expectException(QueryException::class);
        ChallengeEntry::factory()->for($challenge)->for($user)->create();
    }

    public function test_one_vote_per_user_per_challenge(): void
    {
        $challenge = Challenge::factory()->create();
        $a = ChallengeEntry::factory()->for($challenge)->create();
        $b = ChallengeEntry::factory()->for($challenge)->create();
        $voter = User::factory()->create();
        ChallengeVote::create(['user_id' => $voter->id, 'challenge_id' => $challenge->id, 'entry_id' => $a->id]);

        $this->expectException(QueryException::class);
        ChallengeVote::create(['user_id' => $voter->id, 'challenge_id' => $challenge->id, 'entry_id' => $b->id]);
    }
}

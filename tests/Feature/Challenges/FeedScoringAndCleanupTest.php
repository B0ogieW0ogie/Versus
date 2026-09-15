<?php

namespace Tests\Feature\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeVote;
use App\Models\User;
use App\Services\Challenges\FeedScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FeedScoringAndCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresher_and_busier_and_ending_soon_score_higher(): void
    {
        $scorer = app(FeedScorer::class);
        $now = now();

        $fresh = Challenge::factory()->make(['created_at' => $now, 'ends_at' => $now->copy()->addDays(2)]);
        $old = Challenge::factory()->make(['created_at' => $now->copy()->subDays(3), 'ends_at' => $now->copy()->addDays(2)]);
        $endingSoon = Challenge::factory()->make(['created_at' => $now->copy()->subDays(3), 'ends_at' => $now->copy()->addHour()]);

        $this->assertGreaterThan($scorer->score($old, 0, $now), $scorer->score($fresh, 0, $now));
        $this->assertGreaterThan($scorer->score($old, 0, $now), $scorer->score($old, 10, $now));
        $this->assertGreaterThan($scorer->score($old, 0, $now), $scorer->score($endingSoon, 0, $now));
    }

    public function test_recalculate_counts_recent_responses_and_votes(): void
    {
        $quiet = Challenge::factory()->withOriginal()->create(['created_at' => now()->subDay()]);
        $busy = Challenge::factory()->withOriginal()->create(['created_at' => now()->subDay()]);
        $entry = ChallengeEntry::factory()->for($busy)->create();
        ChallengeVote::create(['user_id' => User::factory()->create()->id, 'challenge_id' => $busy->id, 'entry_id' => $entry->id]);

        $this->artisan('challenges:score-feed')->assertSuccessful();

        $this->assertGreaterThan($quiet->fresh()->feed_score, $busy->fresh()->feed_score);
    }

    public function test_cleanup_removes_stale_uploads_and_fails_stuck_entries(): void
    {
        Storage::fake('local');
        Notification::fake();
        Storage::disk('local')->put('uploads/11111111-1111-1111-1111-111111111111/source', 'old');
        Storage::disk('local')->put('uploads/22222222-2222-2222-2222-222222222222/source', 'new');
        touch(Storage::disk('local')->path('uploads/11111111-1111-1111-1111-111111111111'), now()->subDays(2)->getTimestamp());

        $challenge = Challenge::factory()->processing()->create();
        $stuck = ChallengeEntry::factory()->original()->processing()->create([
            'challenge_id' => $challenge->id,
            'user_id' => $challenge->user_id,
            'created_at' => now()->subDays(2),
        ]);

        $this->artisan('challenges:cleanup-uploads')->assertSuccessful();

        Storage::disk('local')->assertMissing('uploads/11111111-1111-1111-1111-111111111111');
        Storage::disk('local')->assertExists('uploads/22222222-2222-2222-2222-222222222222/source');
        $this->assertSame(ChallengeEntry::STATUS_FAILED, $stuck->fresh()->status);
    }
}

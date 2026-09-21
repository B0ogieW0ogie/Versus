<?php

namespace Tests\Feature\Challenges;

use App\Actions\Challenges\MarkEntryFailedAction;
use App\Actions\Challenges\MarkEntryReadyAction;
use App\Jobs\ProcessEntryVideo;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\User;
use App\Notifications\ChallengePublished;
use App\Notifications\ChallengeResponseReceived;
use App\Notifications\DuelInvitation;
use App\Notifications\EntryProcessingFailed;
use App\Notifications\ResponsePublished;
use App\Services\Video\VideoTranscoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeVideoTranscoder;
use Tests\TestCase;

class ProcessEntryVideoTest extends TestCase
{
    use RefreshDatabase;

    private FakeVideoTranscoder $transcoder;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Notification::fake();
        $this->transcoder = new FakeVideoTranscoder;
        $this->app->instance(VideoTranscoder::class, $this->transcoder);
    }

    private function pendingEntry(Challenge $challenge, bool $original, ?User $user = null): ChallengeEntry
    {
        $entry = ChallengeEntry::factory()->processing()->create([
            'challenge_id' => $challenge->id,
            'user_id' => $user?->id ?? $challenge->user_id,
            'is_original' => $original,
        ]);
        $entry->update(['source_path' => "entries/{$entry->id}/source"]);
        Storage::disk('local')->put($entry->source_path, 'raw');

        return $entry;
    }

    public function test_ready_original_activates_challenge_and_starts_the_clock(): void
    {
        $this->travelTo(now()->startOfMinute());
        $challenge = Challenge::factory()->processing()->create(['duration' => Challenge::DURATION_3D]);
        $entry = $this->pendingEntry($challenge, true);

        (new ProcessEntryVideo($entry->id))->handle(...$this->handleArgs());

        $entry->refresh();
        $challenge->refresh();
        $this->assertSame(ChallengeEntry::STATUS_READY, $entry->status);
        $this->assertNull($entry->source_path);
        $this->assertSame(15000, $entry->duration_ms);
        Storage::disk('public')->assertExists([$entry->video_path, $entry->poster_path]);
        Storage::disk('local')->assertMissing("entries/{$entry->id}/source");
        $this->assertSame(Challenge::STATUS_ACTIVE, $challenge->status);
        $this->assertTrue($challenge->ends_at->equalTo(now()->addDays(3)));
        Notification::assertSentTo($challenge->user, ChallengePublished::class);
    }

    public function test_ready_response_bumps_count_and_notifies_both_sides(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $responder = User::factory()->create();
        $entry = $this->pendingEntry($challenge, false, $responder);

        (new ProcessEntryVideo($entry->id))->handle(...$this->handleArgs());

        $this->assertSame(1, $challenge->fresh()->entries_count);
        Notification::assertSentTo($challenge->user, ChallengeResponseReceived::class);
        Notification::assertSentTo($responder, ResponsePublished::class);
    }

    public function test_too_long_original_fails_challenge_without_retry(): void
    {
        $this->transcoder->durationMs = 61_000;
        $challenge = Challenge::factory()->processing()->create();
        $entry = $this->pendingEntry($challenge, true);

        (new ProcessEntryVideo($entry->id))->handle(...$this->handleArgs());

        $this->assertSame(ChallengeEntry::STATUS_FAILED, $entry->fresh()->status);
        $this->assertSame('reason_too_long', $entry->fresh()->failure_reason);
        $this->assertSame(Challenge::STATUS_FAILED, $challenge->fresh()->status);
        Notification::assertSentTo($challenge->user, EntryProcessingFailed::class);
    }

    public function test_failed_response_is_deleted_so_the_user_can_retry(): void
    {
        $this->transcoder->hasVideo = false;
        $challenge = Challenge::factory()->withOriginal()->create();
        $responder = User::factory()->create();
        $entry = $this->pendingEntry($challenge, false, $responder);

        (new ProcessEntryVideo($entry->id))->handle(...$this->handleArgs());

        $this->assertNull(ChallengeEntry::find($entry->id));
        Storage::disk('local')->assertMissing("entries/{$entry->id}/source");
        $this->assertSame(Challenge::STATUS_ACTIVE, $challenge->fresh()->status);
        Notification::assertSentTo($responder, EntryProcessingFailed::class);
    }

    public function test_exhausted_retries_mark_generic_failure(): void
    {
        $challenge = Challenge::factory()->processing()->create();
        $entry = $this->pendingEntry($challenge, true);

        (new ProcessEntryVideo($entry->id))->failed(new \RuntimeException('worker died'));

        $this->assertSame('reason_generic', $entry->fresh()->failure_reason);
    }

    public function test_already_processed_entry_is_skipped(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();

        (new ProcessEntryVideo($challenge->original->id))->handle(...$this->handleArgs());

        Notification::assertNothingSent();
    }

    /** @return array{0: VideoTranscoder, 1: MarkEntryReadyAction, 2: MarkEntryFailedAction} */
    private function handleArgs(): array
    {
        return [
            app(VideoTranscoder::class),
            app(MarkEntryReadyAction::class),
            app(MarkEntryFailedAction::class),
        ];
    }

    public function test_ready_duel_invites_the_opponent(): void
    {
        $rival = User::factory()->create();
        $challenge = Challenge::factory()->processing()->duel($rival)->create();
        $entry = $this->pendingEntry($challenge, true);

        (new ProcessEntryVideo($entry->id))->handle(...$this->handleArgs());

        Notification::assertSentTo($rival, DuelInvitation::class);
    }
}

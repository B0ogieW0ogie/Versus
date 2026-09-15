<?php

namespace Tests\Feature\Challenges;

use App\Actions\Challenges\AcceptChallengeAction;
use App\Actions\Challenges\LeaveChallengeAction;
use App\Actions\Challenges\SubmitResponseAction;
use App\Jobs\ProcessEntryVideo;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeParticipant;
use App\Models\User;
use App\Services\Video\UploadStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ParticipationActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
    }

    private function upload(User $user): string
    {
        $store = app(UploadStore::class);
        $id = $store->start($user);
        $store->appendChunk($user, $id, 0, UploadedFile::fake()->createWithContent('c', 'bytes'));
        $store->complete($user, $id);

        return $id;
    }

    private function assertChallengeError(callable $fn, string $message): void
    {
        try {
            $fn();
            $this->fail('Expected validation error');
        } catch (ValidationException $e) {
            $this->assertSame($message, $e->errors()['challenge'][0] ?? null);
        }
    }

    public function test_response_creates_processing_entry_participant_and_job(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $user = User::factory()->create();

        $entry = app(SubmitResponseAction::class)($user, $challenge, $this->upload($user));

        $this->assertFalse($entry->is_original);
        $this->assertSame(ChallengeEntry::STATUS_PROCESSING, $entry->status);
        Storage::disk('local')->assertExists("entries/{$entry->id}/source");
        $this->assertTrue(ChallengeParticipant::where(['user_id' => $user->id, 'challenge_id' => $challenge->id])->exists());
        Queue::assertPushed(ProcessEntryVideo::class);
    }

    public function test_response_after_deadline_is_rejected_and_upload_deleted(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create(['ends_at' => now()->subSecond()]);
        $user = User::factory()->create();
        $uploadId = $this->upload($user);

        $this->assertChallengeError(
            fn () => app(SubmitResponseAction::class)($user, $challenge, $uploadId),
            __('challenges.not_open'),
        );
        Storage::disk('local')->assertMissing(app(UploadStore::class)->dir($uploadId));
    }

    public function test_author_cannot_respond_to_own_challenge(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();

        $this->assertChallengeError(
            fn () => app(SubmitResponseAction::class)($challenge->user, $challenge, $this->upload($challenge->user)),
            __('challenges.own_challenge'),
        );
    }

    public function test_second_response_is_rejected(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $user = User::factory()->create();
        ChallengeEntry::factory()->for($challenge)->for($user)->create();

        $this->assertChallengeError(
            fn () => app(SubmitResponseAction::class)($user, $challenge, $this->upload($user)),
            __('challenges.already_responded'),
        );
    }

    public function test_accept_is_idempotent_and_blocked_for_author_and_closed(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $user = User::factory()->create();

        app(AcceptChallengeAction::class)($user, $challenge);
        app(AcceptChallengeAction::class)($user, $challenge);
        $this->assertSame(1, ChallengeParticipant::count());

        $this->assertChallengeError(fn () => app(AcceptChallengeAction::class)($challenge->user, $challenge), __('challenges.own_challenge'));
        $closed = Challenge::factory()->closed()->create();
        $this->assertChallengeError(fn () => app(AcceptChallengeAction::class)($user, $closed), __('challenges.not_open'));
    }

    public function test_leave_removes_participation_unless_user_responded(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $user = User::factory()->create();
        app(AcceptChallengeAction::class)($user, $challenge);

        app(LeaveChallengeAction::class)($user, $challenge);
        $this->assertSame(0, ChallengeParticipant::count());

        app(AcceptChallengeAction::class)($user, $challenge);
        ChallengeEntry::factory()->for($challenge)->for($user)->create();
        $this->assertChallengeError(fn () => app(LeaveChallengeAction::class)($user, $challenge), __('challenges.cannot_leave_with_entry'));
    }
}

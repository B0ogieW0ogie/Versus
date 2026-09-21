<?php

namespace Tests\Feature\Challenges;

use App\Actions\Challenges\CreateChallengeAction;
use App\Jobs\ProcessEntryVideo;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\User;
use App\Services\Video\UploadStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreateChallengeActionTest extends TestCase
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
        $store->appendChunk($user, $id, 0, UploadedFile::fake()->createWithContent('c', 'video-bytes'));
        $store->complete($user, $id);

        return $id;
    }

    private function create(User $user, array $overrides = []): Challenge
    {
        $args = array_merge([
            'uploadId' => $this->upload($user),
            'title' => 'Kickflip clean',
            'rules' => 'Land it in 10 seconds #skate',
            'category' => Challenge::CATEGORY_SPORTS,
            'duration' => Challenge::DURATION_3D,
            'format' => Challenge::FORMAT_PUBLIC,
            'opponent' => null,
            'username' => null,
            'replacing' => null,
        ], $overrides);

        return app(CreateChallengeAction::class)($user, ...$args);
    }

    public function test_creates_processing_challenge_with_original_and_queues_job(): void
    {
        $user = User::factory()->create(['username' => 'dan']);

        $challenge = $this->create($user);

        $this->assertSame(Challenge::STATUS_PROCESSING, $challenge->status);
        $this->assertNull($challenge->ends_at);
        $this->assertStringStartsWith('kickflip-clean-', $challenge->slug);
        $entry = $challenge->original;
        $this->assertSame(ChallengeEntry::STATUS_PROCESSING, $entry->status);
        $this->assertSame("entries/{$entry->id}/source", $entry->source_path);
        Storage::disk('local')->assertExists($entry->source_path);
        Queue::assertPushed(ProcessEntryVideo::class, fn (ProcessEntryVideo $job) => $job->entryId === $entry->id);
    }

    public function test_video_job_goes_to_the_dedicated_videos_queue(): void
    {
        $this->create(User::factory()->create(['username' => 'dan']));

        Queue::assertPushedOn('videos', ProcessEntryVideo::class);
    }

    public function test_invalid_fields_do_not_consume_the_upload(): void
    {
        $user = User::factory()->create(['username' => 'dan']);
        $uploadId = $this->upload($user);

        try {
            $this->create($user, ['uploadId' => $uploadId, 'title' => '   ']);
            $this->fail('Expected validation error');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('title', $e->errors());
        }

        $this->assertSame('video-bytes', Storage::disk('local')->get(app(UploadStore::class)->claim($user, $uploadId)));
    }

    public function test_unknown_duration_is_rejected(): void
    {
        $user = User::factory()->create(['username' => 'dan']);

        $this->expectException(ValidationException::class);
        $this->create($user, ['duration' => '1y']);
    }

    public function test_username_is_required_and_saved_when_missing(): void
    {
        $user = User::factory()->create(['username' => null]);

        try {
            $this->create($user);
            $this->fail('Expected validation error');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('username', $e->errors());
        }

        $this->create($user, ['username' => 'fresh_name']);
        $this->assertSame('fresh_name', $user->fresh()->username);
    }

    public function test_taken_username_is_rejected(): void
    {
        User::factory()->create(['username' => 'taken']);
        $user = User::factory()->create(['username' => null]);

        $this->expectException(ValidationException::class);
        $this->create($user, ['username' => 'taken']);
    }

    public function test_daily_limit_ignores_failed_challenges(): void
    {
        config(['versus.challenges.daily_create_limit' => 2]);
        $user = User::factory()->create(['username' => 'dan']);
        Challenge::factory()->for($user)->count(2)->failed()->create();
        $this->create($user);
        $this->create($user);

        try {
            $this->create($user);
            $this->fail('Expected daily limit');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('daily_limit', $e->errors());
        }
    }

    public function test_replacing_a_failed_challenge_deletes_it(): void
    {
        $user = User::factory()->create(['username' => 'dan']);
        $failed = Challenge::factory()->for($user)->failed()->create();

        $this->create($user, ['replacing' => $failed]);

        $this->assertNull(Challenge::find($failed->id));
    }

    public function test_cannot_replace_someone_elses_or_active_challenge(): void
    {
        $user = User::factory()->create(['username' => 'dan']);
        $active = Challenge::factory()->for($user)->create();

        $this->expectException(ValidationException::class);
        $this->create($user, ['replacing' => $active]);
    }

    public function test_stores_category_and_public_format(): void
    {
        $challenge = $this->create(User::factory()->create(['username' => 'dan']));

        $this->assertSame(Challenge::CATEGORY_SPORTS, $challenge->category);
        $this->assertSame(Challenge::FORMAT_PUBLIC, $challenge->format);
        $this->assertNull($challenge->opponent_id);
    }

    public function test_category_is_required_and_must_be_known(): void
    {
        $user = User::factory()->create(['username' => 'dan']);

        foreach (['', 'memes'] as $category) {
            try {
                $this->create($user, ['category' => $category]);
                $this->fail('Expected validation error');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('category', $e->errors());
            }
        }
    }

    public function test_offered_durations_are_3_7_14_30_days(): void
    {
        $user = User::factory()->create(['username' => 'dan']);

        $this->assertSame(['3d', '7d', '14d', '30d'], array_keys(config('versus.challenges.durations')));
        $this->assertSame('30d', $this->create($user, ['duration' => Challenge::DURATION_30D])->duration);

        $this->expectException(ValidationException::class);
        $this->create($user, ['duration' => '24h']);
    }

    public function test_duel_stores_the_opponent(): void
    {
        $user = User::factory()->create(['username' => 'dan']);
        $rival = User::factory()->create();

        $challenge = $this->create($user, ['format' => Challenge::FORMAT_DUEL, 'opponent' => $rival]);

        $this->assertTrue($challenge->isDuel());
        $this->assertSame($rival->id, $challenge->opponent_id);
    }

    public function test_duel_requires_an_opponent_other_than_the_creator(): void
    {
        $user = User::factory()->create(['username' => 'dan']);

        foreach ([null, $user] as $opponent) {
            try {
                $this->create($user, ['format' => Challenge::FORMAT_DUEL, 'opponent' => $opponent]);
                $this->fail('Expected validation error');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('opponent', $e->errors());
            }
        }
    }

    public function test_public_challenge_drops_any_opponent(): void
    {
        $user = User::factory()->create(['username' => 'dan']);

        $challenge = $this->create($user, ['opponent' => User::factory()->create()]);

        $this->assertNull($challenge->opponent_id);
    }

    public function test_unknown_format_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->create(User::factory()->create(['username' => 'dan']), ['format' => 'private']);
    }
}

<?php

namespace Tests\Feature\Challenges;

use App\Models\User;
use App\Services\Video\UploadStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ChallengeUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_chunks_are_appended_in_order_and_claimable_after_complete(): void
    {
        $user = User::factory()->create();

        $id = $this->actingAs($user)->postJson(route('challenge-uploads.start'))
            ->assertOk()
            ->assertJsonPath('chunk_bytes', 5 * 1024 * 1024)
            ->json('upload_id');

        $this->postJson(route('challenge-uploads.chunk', $id), ['index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c0', 'AAA')])
            ->assertOk()->assertJsonPath('next_chunk', 1);
        // A resent chunk (network retry) is ignored, not duplicated.
        $this->postJson(route('challenge-uploads.chunk', $id), ['index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c0', 'AAA')])
            ->assertOk()->assertJsonPath('next_chunk', 1);
        $this->postJson(route('challenge-uploads.chunk', $id), ['index' => 1, 'chunk' => UploadedFile::fake()->createWithContent('c1', 'BB')])
            ->assertOk()->assertJsonPath('size', 5);

        $this->postJson(route('challenge-uploads.complete', $id))->assertOk()->assertJsonPath('completed', true);

        $path = app(UploadStore::class)->claim($user, $id);
        $this->assertSame('AAABB', Storage::disk('local')->get($path));
    }

    public function test_starting_uploads_is_rate_limited_per_user(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->postJson(route('challenge-uploads.start'))->assertOk();
        }

        $this->actingAs($user)->postJson(route('challenge-uploads.start'))->assertStatus(429);
        // The limiter is keyed per user, not global.
        $this->actingAs(User::factory()->create())->postJson(route('challenge-uploads.start'))->assertOk();
    }

    public function test_out_of_order_chunk_is_rejected(): void
    {
        $user = User::factory()->create();
        $id = $this->actingAs($user)->postJson(route('challenge-uploads.start'))->json('upload_id');

        $this->postJson(route('challenge-uploads.chunk', $id), ['index' => 2, 'chunk' => UploadedFile::fake()->createWithContent('c', 'A')])
            ->assertStatus(422);
    }

    public function test_another_user_cannot_touch_or_claim_the_upload(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $id = $this->actingAs($owner)->postJson(route('challenge-uploads.start'))->json('upload_id');

        $this->actingAs($intruder)
            ->postJson(route('challenge-uploads.chunk', $id), ['index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c', 'A')])
            ->assertStatus(422);

        $this->expectException(ValidationException::class);
        app(UploadStore::class)->claim($intruder, $id);
    }

    public function test_upload_over_the_size_limit_is_rejected(): void
    {
        config(['versus.challenges.max_upload_mb' => 0]);
        $user = User::factory()->create();
        $id = $this->actingAs($user)->postJson(route('challenge-uploads.start'))->json('upload_id');

        $this->postJson(route('challenge-uploads.chunk', $id), ['index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c', 'A')])
            ->assertStatus(422);
    }

    public function test_incomplete_upload_cannot_be_claimed(): void
    {
        $user = User::factory()->create();
        $store = app(UploadStore::class);
        $id = $store->start($user);

        $this->expectException(ValidationException::class);
        $store->claim($user, $id);
    }

    public function test_guest_cannot_start_upload(): void
    {
        $this->postJson(route('challenge-uploads.start'))->assertUnauthorized();
    }
}

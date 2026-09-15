<?php

namespace Tests\Feature\Challenges;

use App\Livewire\ChallengeForm;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\User;
use App\Services\Video\UploadStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ChallengeFormTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('challenges.create'))->assertRedirect(route('login'));
    }

    public function test_publishing_a_new_challenge(): void
    {
        $user = User::factory()->create(['username' => 'dan']);

        Livewire::actingAs($user)
            ->test(ChallengeForm::class)
            ->set('uploadId', $this->upload($user))
            ->set('title', 'Kickflip clean')
            ->set('rules', 'Land it')
            ->set('duration', Challenge::DURATION_24H)
            ->call('publish')
            ->assertHasNoErrors()
            ->assertRedirect(route('challenges.mine'));

        $this->assertSame('Kickflip clean', Challenge::sole()->title);
    }

    public function test_missing_video_and_fields_show_errors(): void
    {
        $user = User::factory()->create(['username' => 'dan']);

        Livewire::actingAs($user)->test(ChallengeForm::class)
            ->call('publish')
            ->assertHasErrors(['uploadId']);

        Livewire::actingAs($user)->test(ChallengeForm::class)
            ->set('uploadId', $this->upload($user))
            ->call('publish')
            ->assertHasErrors(['title', 'rules', 'duration']);
    }

    public function test_user_without_username_sees_the_field(): void
    {
        $user = User::factory()->create(['username' => null]);

        Livewire::actingAs($user)->test(ChallengeForm::class)
            ->assertSee(__('challenges.field_username'));
    }

    public function test_respond_mode_prefills_locked_fields_and_publishes_response(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create(['title' => 'Play this riff', 'rules' => 'Clean']);
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ChallengeForm::class, ['challenge' => $challenge])
            ->assertSet('title', 'Play this riff')
            ->assertSee(__('challenges.publish_response'))
            ->set('title', 'hacked')
            ->set('uploadId', $this->upload($user))
            ->call('publish')
            ->assertRedirect(route('challenges.mine'));

        $this->assertSame('Play this riff', $challenge->fresh()->title);
        $this->assertTrue(ChallengeEntry::where('challenge_id', $challenge->id)->where('user_id', $user->id)->exists());
    }

    public function test_respond_to_closed_challenge_shows_error(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create(['ends_at' => now()->subMinute()]);
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ChallengeForm::class, ['challenge' => $challenge])
            ->set('uploadId', $this->upload($user))
            ->call('publish')
            ->assertHasErrors(['challenge']);
    }

    public function test_retry_prefills_from_failed_challenge_and_replaces_it(): void
    {
        $user = User::factory()->create(['username' => 'dan']);
        $failed = Challenge::factory()->for($user)->failed()->create(['title' => 'Try again', 'rules' => 'Rules', 'duration' => Challenge::DURATION_7D]);

        Livewire::withQueryParams(['retry' => $failed->slug])
            ->actingAs($user)
            ->test(ChallengeForm::class)
            ->assertSet('title', 'Try again')
            ->assertSet('duration', Challenge::DURATION_7D)
            ->set('uploadId', $this->upload($user))
            ->call('publish')
            ->assertHasNoErrors();

        $this->assertNull(Challenge::find($failed->id));
    }
}

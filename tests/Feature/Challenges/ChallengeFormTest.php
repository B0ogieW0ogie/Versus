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

    public function test_back_url_is_computed_once_and_defaults_to_the_feed(): void
    {
        $user = User::factory()->create(['username' => 'dan']);

        Livewire::actingAs($user)
            ->test(ChallengeForm::class)
            ->assertSet('backUrl', route('challenges.index'))
            ->set('title', 'Changed')
            ->assertSet('backUrl', route('challenges.index'));
    }

    public function test_publishing_a_new_challenge(): void
    {
        $user = User::factory()->create(['username' => 'dan']);

        Livewire::actingAs($user)
            ->test(ChallengeForm::class)
            ->set('uploadId', $this->upload($user))
            ->set('title', 'Kickflip clean')
            ->set('rules', 'Land it')
            ->set('category', Challenge::CATEGORY_SPORTS)
            ->call('publish')
            ->assertHasNoErrors()
            ->assertRedirect(route('challenges.mine'));

        $challenge = Challenge::sole();
        $this->assertSame('Kickflip clean', $challenge->title);
        $this->assertSame(Challenge::DURATION_7D, $challenge->duration); // the default
        $this->assertSame(Challenge::FORMAT_PUBLIC, $challenge->format);
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
            ->assertHasErrors(['title', 'rules', 'category']);
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

    public function test_respond_to_processing_challenge_is_not_found(): void
    {
        $challenge = Challenge::factory()->processing()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('challenges.respond', $challenge->slug))
            ->assertNotFound();
    }

    public function test_respond_to_failed_challenge_is_not_found(): void
    {
        $challenge = Challenge::factory()->failed()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('challenges.respond', $challenge->slug))
            ->assertNotFound();
    }

    public function test_retry_prefills_from_failed_challenge_and_replaces_it(): void
    {
        $user = User::factory()->create(['username' => 'dan']);
        $rival = User::factory()->create();
        $failed = Challenge::factory()->for($user)->failed()->duel($rival)->create([
            'title' => 'Try again', 'rules' => 'Rules', 'duration' => Challenge::DURATION_14D, 'category' => Challenge::CATEGORY_MUSIC,
        ]);

        Livewire::withQueryParams(['retry' => $failed->slug])
            ->actingAs($user)
            ->test(ChallengeForm::class)
            ->assertSet('title', 'Try again')
            ->assertSet('duration', Challenge::DURATION_14D)
            ->assertSet('category', Challenge::CATEGORY_MUSIC)
            ->assertSet('format', Challenge::FORMAT_DUEL)
            ->assertSet('opponentId', $rival->id)
            ->set('uploadId', $this->upload($user))
            ->call('publish')
            ->assertHasNoErrors();

        $this->assertNull(Challenge::find($failed->id));
    }

    public function test_form_shows_category_deadline_and_format_without_battle_mechanics(): void
    {
        $user = User::factory()->create(['username' => 'dan']);

        $html = $this->actingAs($user)->get(route('challenges.create'))->assertOk()->getContent();

        foreach (Challenge::CATEGORIES as $category) {
            $this->assertStringContainsString(e(__('challenges.category_'.$category)), $html);
        }
        foreach (['3d', '7d', '14d', '30d'] as $duration) {
            $this->assertStringContainsString(__('challenges.duration_'.$duration), $html);
        }
        $this->assertStringContainsString(__('challenges.format_public'), $html);
        $this->assertStringContainsString(__('challenges.format_duel'), $html);
        $this->assertStringNotContainsString('data-field="opponent"', $html);
        $this->assertStringNotContainsString('Private', $html);
        // No stake/token/prize-pool inputs from the Battle mechanics.
        $this->assertDoesNotMatchRegularExpression('/wire:model[^=]*="(amount|stake|side|pool)/', $html);
    }

    public function test_page_uses_the_desktop_shell_without_the_top_nav_on_lg(): void
    {
        $user = User::factory()->create(['username' => 'dan']);

        $this->actingAs($user)->get(route('challenges.create'))
            ->assertOk()
            ->assertSee('data-nav="top" class="hidden"', false)
            ->assertSee('data-side-nav', false)
            ->assertSee('data-video-zone', false)
            ->assertSee('aspect-[9/16]', false);
    }

    public function test_duel_shows_opponent_search_and_public_hides_it(): void
    {
        $user = User::factory()->create(['username' => 'dan']);
        $rival = User::factory()->create(['username' => 'rival_one', 'name' => 'Rival']);
        User::factory()->create(['username' => 'someone']);

        Livewire::actingAs($user)->test(ChallengeForm::class)
            ->assertDontSee(__('challenges.field_opponent'))
            ->set('format', Challenge::FORMAT_DUEL)
            ->assertSee(__('challenges.field_opponent'))
            ->set('opponentQuery', '@riv')
            ->assertSee('@rival_one')
            ->assertDontSee('@someone')
            ->call('selectOpponent', $rival->id)
            ->assertSet('opponentId', $rival->id)
            ->set('format', Challenge::FORMAT_PUBLIC)
            ->assertSet('opponentId', null)
            ->assertDontSee(__('challenges.field_opponent'));
    }

    public function test_opponent_search_excludes_the_creator_and_self_selection_is_ignored(): void
    {
        $user = User::factory()->create(['username' => 'dan_self']);

        Livewire::actingAs($user)->test(ChallengeForm::class)
            ->set('format', Challenge::FORMAT_DUEL)
            ->set('opponentQuery', 'dan')
            ->assertSee(__('challenges.opponent_none'))
            ->call('selectOpponent', $user->id)
            ->assertSet('opponentId', null);
    }

    public function test_publishing_a_duel(): void
    {
        $user = User::factory()->create(['username' => 'dan']);
        $rival = User::factory()->create();

        Livewire::actingAs($user)->test(ChallengeForm::class)
            ->set('uploadId', $this->upload($user))
            ->set('title', 'Beat me')
            ->set('rules', '1v1')
            ->set('category', Challenge::CATEGORY_GAMING)
            ->set('format', Challenge::FORMAT_DUEL)
            ->call('publish')
            ->assertHasErrors(['opponent'])
            ->call('selectOpponent', $rival->id)
            ->call('publish')
            ->assertHasNoErrors()
            ->assertRedirect(route('challenges.mine'));

        $this->assertSame($rival->id, Challenge::sole()->opponent_id);
    }

    public function test_only_the_duel_opponent_can_open_the_respond_form(): void
    {
        $rival = User::factory()->create();
        $duel = Challenge::factory()->duel($rival)->withOriginal()->create();

        $this->actingAs(User::factory()->create())->get(route('challenges.respond', $duel->slug))->assertForbidden();
        $this->actingAs($rival)->get(route('challenges.respond', $duel->slug))->assertOk();
    }

    public function test_form_hides_all_global_navigation(): void
    {
        $user = User::factory()->create(['username' => 'dan']);

        $this->actingAs($user)->get(route('challenges.create'))
            ->assertOk()
            ->assertSee('data-nav="top" class="hidden"', false)
            ->assertDontSee('fixed bottom-0 inset-x-0 z-40', false)
            ->assertSee('data-video-empty', false);
    }

    public function test_trim_is_stored_on_the_original_entry(): void
    {
        $user = User::factory()->create(['username' => 'dan']);

        Livewire::actingAs($user)->test(ChallengeForm::class)
            ->set('uploadId', $this->upload($user))
            ->set('title', 'Trimmed')
            ->set('rules', 'Rules')
            ->set('category', Challenge::CATEGORY_OTHER)
            ->set('trimStartMs', 2000)
            ->set('trimEndMs', 14000)
            ->call('publish')
            ->assertHasNoErrors();

        $entry = Challenge::sole()->original;
        $this->assertSame([2000, 14000], [$entry->trim_start_ms, $entry->trim_end_ms]);
    }

    public function test_invalid_trim_is_rejected_without_consuming_the_upload(): void
    {
        $user = User::factory()->create(['username' => 'dan']);

        Livewire::actingAs($user)->test(ChallengeForm::class)
            ->set('uploadId', $uploadId = $this->upload($user))
            ->set('title', 'Trimmed')
            ->set('rules', 'Rules')
            ->set('category', Challenge::CATEGORY_OTHER)
            ->set('trimStartMs', 0)
            ->set('trimEndMs', 90000) // longer than the 60 s limit
            ->call('publish')
            ->assertHasErrors(['trim'])
            ->assertSet('uploadId', $uploadId);

        $this->assertSame(0, Challenge::count());
    }

    public function test_response_mode_shows_inherited_locked_conditions(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create(['category' => Challenge::CATEGORY_MUSIC]);

        $this->actingAs(User::factory()->create())->get(route('challenges.respond', $challenge->slug))
            ->assertOk()
            ->assertSee(__('challenges.response_title'))
            ->assertSee('data-locked="category"', false)
            ->assertSee(__('challenges.category_music'))
            ->assertSee('data-locked="deadline"', false)
            ->assertDontSee('data-field="format"', false)
            ->assertDontSee('data-field="category"', false);
    }

    public function test_response_trim_is_stored(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(ChallengeForm::class, ['challenge' => $challenge])
            ->set('uploadId', $this->upload($user))
            ->set('trimStartMs', 500)
            ->set('trimEndMs', 5500)
            ->call('publish')
            ->assertHasNoErrors();

        $entry = ChallengeEntry::where('challenge_id', $challenge->id)->where('user_id', $user->id)->sole();
        $this->assertSame([500, 5500], [$entry->trim_start_ms, $entry->trim_end_ms]);
    }

    public function test_creator_cannot_open_respond_and_a_second_response_is_not_offered(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $this->actingAs($challenge->user)->get(route('challenges.respond', $challenge->slug))->assertForbidden();

        $responder = User::factory()->create();
        $entry = ChallengeEntry::factory()->for($challenge)->for($responder)->create();
        $this->actingAs($responder)->get(route('challenges.respond', $challenge->slug))
            ->assertRedirect(route('challenges.show', ['challenge' => $challenge->slug, 'entry' => $entry->id]));
    }
}

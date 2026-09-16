<?php

namespace Tests\Feature\Challenges;

use App\Livewire\ChallengeFeed;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeParticipant;
use App\Models\ChallengeView;
use App\Models\ChallengeVote;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChallengeFeedPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_the_feed_and_deep_link(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create(['title' => 'Kickflip clean']);
        $entry = ChallengeEntry::factory()->for($challenge)->create();

        $this->get(route('challenges.index'))->assertOk()->assertSee('Kickflip clean')
            ->assertSee('snap-always', false)
            ->assertDontSee('Tap to unmute');
        $this->get(route('challenges.show', ['challenge' => $challenge->slug, 'entry' => $entry->id]))->assertOk();

        // @js() escapes quotes, so assert on component state rather than raw HTML.
        Livewire::withQueryParams(['entry' => $entry->id])
            ->test(ChallengeFeed::class, ['challenge' => $challenge])
            ->assertSet('initialSlides.0.id', $challenge->id)
            ->assertSet('initialSlides.0.focus_index', 1);
    }

    public function test_feed_renders_desktop_columns_and_side_nav(): void
    {
        config(['versus.battles_enabled' => false]);
        Challenge::factory()->withOriginal()->create();

        $html = $this->get(route('challenges.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-side-nav', $html);
        $this->assertStringContainsString('data-desktop-details', $html);
        $this->assertStringContainsString('data-desktop-comments', $html);
        $this->assertStringContainsString(__('challenges.may_like_title'), $html);
        foreach ([__('nav.home'), __('challenges.nav_challenges'), __('challenges.nav_my'), __('nav.profile')] as $label) {
            $this->assertStringContainsString($label, $html);
        }
        $this->assertStringContainsString('href="'.route('challenges.index').'"', $html);
        $this->assertStringNotContainsString('href="'.route('leaderboard').'"', $html);
        $this->assertStringNotContainsString('href="'.route('battles.create').'"', $html);
        $this->assertDoesNotMatchRegularExpression('#href="[^"]*/battles#', $html);
        // Guest: the side nav offers login/register and the comments column a login link.
        $this->assertStringContainsString(__('challenges.comment_login'), $html);
    }

    public function test_feed_side_nav_holds_the_bell_for_users(): void
    {
        config(['versus.battles_enabled' => false]);

        $html = $this->actingAs(User::factory()->create())->get(route('challenges.index'))->assertOk()->getContent();

        $this->assertStringContainsString(__('challenges.notifications'), $html);
        $this->assertStringContainsString('href="'.route('challenges.mine').'"', $html);
        $this->assertStringContainsString('data-desktop-comment-input', $html);
        $this->assertStringNotContainsString(__('challenges.comment_login'), $html);
    }

    public function test_deep_link_opens_closed_challenge_even_though_feed_hides_it(): void
    {
        $closed = Challenge::factory()->closed()->withOriginal()->create(['title' => 'Old riff']);

        $this->get(route('challenges.index'))->assertDontSee('Old riff');
        $this->get(route('challenges.show', $closed->slug))->assertOk()->assertSee('Old riff');
    }

    public function test_show_processing_or_failed_challenge_returns_404(): void
    {
        $processing = Challenge::factory()->processing()->create();
        $failed = Challenge::factory()->failed()->create();

        $this->get(route('challenges.show', $processing->slug))->assertNotFound();
        $this->get(route('challenges.show', $failed->slug))->assertNotFound();
    }

    public function test_guest_actions_do_not_write(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();

        Livewire::test(ChallengeFeed::class)
            ->call('vote', $challenge->original->id)
            ->call('accept', $challenge->id)
            ->call('toggleLike', $challenge->original->id);

        $this->assertSame(0, ChallengeVote::count());
        $this->assertSame(0, ChallengeParticipant::count());
    }

    public function test_user_can_vote_accept_like_comment_and_mark_viewed(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ChallengeFeed::class)
            ->call('vote', $challenge->original->id)
            ->call('accept', $challenge->id)
            ->call('toggleLike', $challenge->original->id)
            ->call('postComment', $challenge->original->id, 'great')
            ->call('markViewed', $challenge->id)
            ->call('recordImpressions', [$challenge->original->id])
            ->call('dismissSwipeHint');

        $this->assertSame(1, $challenge->original->fresh()->votes_count);
        $this->assertSame(1, $challenge->original->fresh()->likes_count);
        $this->assertSame(1, $challenge->original->fresh()->impressions_count);
        $this->assertTrue(ChallengeParticipant::where('user_id', $user->id)->exists());
        $this->assertTrue(ChallengeView::where('user_id', $user->id)->exists());
        $this->assertSame('great', Comment::where('challenge_entry_id', $challenge->original->id)->value('body'));
        $this->assertNotNull($user->fresh()->swipe_hint_seen_at);
    }

    public function test_load_more_skips_already_loaded_challenges(): void
    {
        Challenge::factory()->withOriginal()->count(7)->create();

        $component = Livewire::test(ChallengeFeed::class);
        $this->assertCount(5, $component->get('loadedIds'));

        $component->call('loadMore');
        $this->assertCount(7, $component->get('loadedIds'));
    }
}

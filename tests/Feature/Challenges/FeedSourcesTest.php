<?php

namespace Tests\Feature\Challenges;

use App\Actions\Challenges\CastChallengeVoteAction;
use App\Actions\Challenges\CloseChallengeAction;
use App\Filament\Admin\Resources\News\Pages\CreateNewsPost;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeVote;
use App\Models\FeedPost;
use App\Models\User;
use App\Services\Challenges\TrackTopResponses;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FeedSourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_vote_and_milestones_award_achievements_once(): void
    {
        config(['versus.achievements.votes' => [1, 2]]);
        $voter = User::factory()->create();
        $a = ChallengeEntry::factory()->for(Challenge::factory()->withOriginal())->create();
        $b = ChallengeEntry::factory()->for(Challenge::factory()->withOriginal())->create();

        app(CastChallengeVoteAction::class)($voter, $a);
        app(CastChallengeVoteAction::class)($voter, $a); // same vote again: no new milestone
        $this->assertSame(['votes_1'], FeedPost::where('type', FeedPost::TYPE_ACHIEVEMENT)->pluck('key')->all());

        app(CastChallengeVoteAction::class)($voter, $b);
        $this->assertSame(['votes_1', 'votes_2'], FeedPost::where('type', FeedPost::TYPE_ACHIEVEMENT)->orderBy('id')->pluck('key')->all());
    }

    public function test_winning_a_challenge_awards_first_win(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create(['ends_at' => now()->subMinute()]);
        $winner = ChallengeEntry::factory()->for($challenge)->create(['votes_count' => 3]);
        ChallengeVote::create(['user_id' => User::factory()->create()->id, 'challenge_id' => $challenge->id, 'entry_id' => $winner->id]);

        app(CloseChallengeAction::class)($challenge);

        $this->assertTrue(FeedPost::where('type', FeedPost::TYPE_ACHIEVEMENT)->where('user_id', $winner->user_id)->where('key', 'wins_1')->exists());
    }

    public function test_top_checkpoint_posts_only_real_moves_inside_the_top(): void
    {
        config(['versus.challenges.top_size' => 3]);
        $challenge = Challenge::factory()->withOriginal()->create();
        [$a, $b, $c, $d] = collect([40, 30, 20, 10])->map(
            fn ($likes, $i) => ChallengeEntry::factory()->for($challenge)->create(['likes_count' => $likes, 'votes_count' => 100 - $i, 'submitted_at' => now()->subMinutes(10 - $i)])
        )->all();
        $tracker = app(TrackTopResponses::class);

        $this->assertSame(0, $tracker->checkpoint($challenge)); // first checkpoint only records
        $this->assertSame([1, 2, 3, null], [$a->fresh()->top_rank, $b->fresh()->top_rank, $c->fresh()->top_rank, $d->fresh()->top_rank]);

        $this->assertSame(0, $tracker->checkpoint($challenge)); // nothing moved

        $c->update(['likes_count' => 50]);  // #3 → #1
        $d->update(['likes_count' => 35]);  // outside → #3 (a #1 → #2, b falls out)
        $votesOnly = $b->fresh();
        $votesOnly->update(['votes_count' => 999]); // votes never affect the Top

        $this->assertSame(3, $tracker->checkpoint($challenge));
        $moves = FeedPost::where('type', FeedPost::TYPE_TOP_CHANGE)->get()->mapWithKeys(fn ($p) => [$p->entry_id => $p->data]);
        $this->assertSame(['from' => 3, 'to' => 1], $moves[$c->id]);
        $this->assertSame(['from' => null, 'to' => 3], $moves[$d->id]);
        $this->assertSame(['from' => 1, 'to' => 2], $moves[$a->id]);
        $this->assertArrayNotHasKey($b->id, $moves->all()); // dropping out of the Top posts nothing
        $this->assertNull($b->fresh()->top_rank);
    }

    public function test_track_top_command_runs_for_active_challenges(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        ChallengeEntry::factory()->for($challenge)->create();

        $this->artisan('challenges:track-top')->assertSuccessful();

        $this->assertNotNull($challenge->fresh()->top_checked_at);
    }

    public function test_new_status_is_saved_and_posted(): void
    {
        $user = User::factory()->create();
        $form = ['name' => $user->name, 'email' => $user->email, 'status' => '  Move more — live more!  '];

        $this->actingAs($user)->patch(route('profile.settings.update'), $form)->assertSessionHasNoErrors();
        $this->actingAs($user)->patch(route('profile.settings.update'), $form); // unchanged: no second post
        $this->actingAs($user)->patch(route('profile.settings.update'), ['status' => ''] + $form); // cleared: no post

        $this->assertNull($user->fresh()->status);
        $this->assertSame(['Move more — live more!'], FeedPost::where('type', FeedPost::TYPE_STATUS)->pluck('body')->all());
    }

    public function test_admin_publishes_versus_news(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Filament::setCurrentPanel('admin');

        Livewire::actingAs($admin)->test(CreateNewsPost::class)
            ->fillForm(['title' => 'New Challenge categories', 'published_at' => now()])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = FeedPost::sole();
        $this->assertSame(FeedPost::TYPE_VERSUS_NEWS, $post->type);
        $this->assertNull($post->user_id);
    }
}

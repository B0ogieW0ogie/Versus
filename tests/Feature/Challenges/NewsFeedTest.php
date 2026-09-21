<?php

namespace Tests\Feature\Challenges;

use App\Livewire\NewsFeed;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeParticipant;
use App\Models\ChallengeVote;
use App\Models\User;
use App\Services\News\NewsEvent;
use App\Services\News\NewsFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NewsFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_collects_challenge_events_newest_first(): void
    {
        $author = User::factory()->create();
        $challenge = Challenge::factory()->for($author)->withOriginal()->create(['created_at' => now()->subHours(5)]);
        $responder = User::factory()->create();
        ChallengeParticipant::create(['user_id' => $responder->id, 'challenge_id' => $challenge->id, 'accepted_at' => now()->subHours(4)]);
        $entry = ChallengeEntry::factory()->for($challenge)->for($responder)->create(['submitted_at' => now()->subHours(3)]);
        $voter = User::factory()->create();
        $vote = ChallengeVote::create(['user_id' => $voter->id, 'challenge_id' => $challenge->id, 'entry_id' => $entry->id]);
        $vote->forceFill(['updated_at' => now()->subHours(2)])->save();

        $events = app(NewsFeedService::class)->events(null);

        $this->assertSame(
            [NewsEvent::TYPE_VOTED, NewsEvent::TYPE_RESPONDED, NewsEvent::TYPE_ACCEPTED, NewsEvent::TYPE_CREATED],
            $events->pluck('type')->all(),
        );
        $this->assertSame($responder->id, $events[1]->actor->id);
        $this->assertStringContainsString('entry='.$entry->id, $events[1]->url());
    }

    public function test_processing_and_failed_challenges_stay_out(): void
    {
        Challenge::factory()->processing()->create();
        Challenge::factory()->failed()->create();

        $this->assertCount(0, app(NewsFeedService::class)->events(null));
    }

    public function test_results_carry_a_podium_of_up_to_three(): void
    {
        $challenge = Challenge::factory()->closed()->withOriginal()->create();
        foreach ([5, 9, 1, 7] as $votes) {
            ChallengeEntry::factory()->for($challenge)->create(['votes_count' => $votes]);
        }

        $results = app(NewsFeedService::class)->events(null, NewsFeedService::SCOPE_ALL, NewsEvent::TYPE_RESULTS);

        $this->assertCount(1, $results);
        $this->assertNull($results[0]->actor);
        $this->assertSame([9, 7, 5], array_map(fn (ChallengeEntry $e) => $e->votes_count, $results[0]->podium));
    }

    public function test_following_scope_limits_to_followed_people(): void
    {
        $viewer = User::factory()->create();
        $friend = User::factory()->create();
        $viewer->following()->attach($friend);
        Challenge::factory()->for($friend)->create();
        Challenge::factory()->create();

        $events = app(NewsFeedService::class)->events($viewer, NewsFeedService::SCOPE_FOLLOWING);

        $this->assertCount(1, $events);
        $this->assertSame($friend->id, $events[0]->actor->id);
    }

    public function test_page_renders_and_filters(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create(['title' => 'Hundred pushups']);
        ChallengeParticipant::create(['user_id' => User::factory()->create()->id, 'challenge_id' => $challenge->id, 'accepted_at' => now()]);

        Livewire::test(NewsFeed::class)
            ->assertSee('Hundred pushups')
            ->assertSee(__('news.event_created'))
            ->assertSee(__('news.event_accepted'))
            ->set('type', NewsEvent::TYPE_ACCEPTED)
            ->assertDontSee(__('news.event_created'))
            ->set('type', 'bogus')
            ->assertSet('type', '');
    }
}

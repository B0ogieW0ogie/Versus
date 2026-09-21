<?php

namespace Tests\Feature\Challenges;

use App\Livewire\NewsFeed;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeParticipant;
use App\Models\ChallengeVote;
use App\Models\FeedPost;
use App\Models\User;
use App\Services\News\NewsEvent;
use App\Services\News\NewsFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NewsFeedTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    private User $friend;

    protected function setUp(): void
    {
        parent::setUp();
        $this->viewer = User::factory()->create();
        $this->friend = User::factory()->create(['username' => 'friend']);
        $this->viewer->following()->attach($this->friend);
    }

    private function feed(string $source = NewsFeedService::SOURCE_ALL, ?string $filter = null)
    {
        return app(NewsFeedService::class)->events($this->viewer, $source, $filter, 50);
    }

    public function test_followed_actions_come_newest_first(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create(['created_at' => now()->subHours(9)]);
        $own = Challenge::factory()->for($this->friend)->withOriginal()->create(['created_at' => now()->subHours(5)]);
        ChallengeParticipant::create(['user_id' => $this->friend->id, 'challenge_id' => $challenge->id, 'accepted_at' => now()->subHours(4)]);
        $entry = ChallengeEntry::factory()->for($challenge)->for($this->friend)->create(['submitted_at' => now()->subHours(3)]);
        $original = ChallengeEntry::where('challenge_id', $challenge->id)->where('is_original', true)->first();
        $vote = ChallengeVote::create(['user_id' => $this->friend->id, 'challenge_id' => $challenge->id, 'entry_id' => $original->id]);
        $vote->forceFill(['updated_at' => now()->subHours(2)])->save();

        $events = $this->feed(NewsFeedService::SOURCE_FOLLOWING);

        $this->assertSame(
            [NewsEvent::TYPE_VOTED, NewsEvent::TYPE_RESPONDED, NewsEvent::TYPE_ACCEPTED, NewsEvent::TYPE_CREATED],
            $events->pluck('type')->all(),
        );
        $this->assertSame($own->id, $events[3]->challenge->id);
    }

    public function test_response_links_to_itself_and_uses_its_own_thumbnail(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $entry = ChallengeEntry::factory()->for($challenge)->for($this->friend)->create(['poster_path' => 'entries/r/poster.jpg']);

        $event = $this->feed(NewsFeedService::SOURCE_FOLLOWING, 'responses')->sole();

        $this->assertStringContainsString('entry='.$entry->id, $event->url());
        $this->assertStringContainsString('entries/r/poster.jpg', (string) $event->posterUrl());
        $this->assertNotSame($challenge->original->posterUrl(), $event->posterUrl());
    }

    public function test_sources(): void
    {
        $stranger = User::factory()->create();
        $interesting = User::factory()->create();
        Challenge::factory()->for($this->friend)->create(['category' => Challenge::CATEGORY_MUSIC]);
        Challenge::factory()->for($stranger)->create();
        $hot = Challenge::factory()->create(['category' => Challenge::CATEGORY_SPORTS]);
        ChallengeEntry::factory()->for($hot)->for($interesting)->create(['votes_count' => 5]);
        FeedPost::create(['type' => FeedPost::TYPE_VERSUS_NEWS, 'title' => 'New categories', 'published_at' => now()]);

        $actors = fn ($events) => $events->map(fn (NewsEvent $e) => $e->actor?->id)->unique()->values()->all();

        $following = $this->feed(NewsFeedService::SOURCE_FOLLOWING);
        $this->assertSame([$this->friend->id], $actors($following));

        $picked = $this->feed(NewsFeedService::SOURCE_INTERESTING);
        $this->assertContains($interesting->id, $actors($picked));
        $this->assertNotContains($stranger->id, $actors($picked));
        $this->assertNotContains(NewsEvent::TYPE_VERSUS_NEWS, $picked->pluck('type')->all());

        $all = $this->feed();
        $this->assertContains($this->friend->id, $actors($all));
        $this->assertContains($interesting->id, $actors($all));
        $this->assertContains(null, $actors($all)); // VERSUS news
        $this->assertNotContains($stranger->id, $actors($all));
    }

    public function test_viewer_own_actions_are_not_in_the_feed(): void
    {
        $this->friend->following()->attach($this->viewer);
        Challenge::factory()->for($this->viewer)->create();

        $this->assertCount(0, $this->feed());
    }

    public function test_type_filters_are_independent_of_the_source(): void
    {
        $challenge = Challenge::factory()->for($this->friend)->withOriginal()->create();
        FeedPost::create(['type' => FeedPost::TYPE_STATUS, 'user_id' => $this->friend->id, 'body' => 'Move more', 'published_at' => now()]);
        FeedPost::create(['type' => FeedPost::TYPE_ACHIEVEMENT, 'user_id' => $this->friend->id, 'key' => 'votes_100', 'published_at' => now()]);
        $entry = ChallengeEntry::factory()->for($challenge)->create();
        FeedPost::create(['type' => FeedPost::TYPE_TOP_CHANGE, 'user_id' => $this->friend->id, 'challenge_id' => $challenge->id, 'entry_id' => $entry->id, 'data' => ['from' => 6, 'to' => 3], 'published_at' => now()]);
        FeedPost::create(['type' => FeedPost::TYPE_VERSUS_NEWS, 'title' => 'News', 'published_at' => now()]);

        $types = fn (?string $filter, string $source = NewsFeedService::SOURCE_ALL) => $this->feed($source, $filter)->pluck('type')->unique()->values()->all();

        $this->assertSame([NewsEvent::TYPE_CREATED], $types('challenges'));
        $this->assertSame([NewsEvent::TYPE_STATUS], $types('statuses'));
        $this->assertSame([NewsEvent::TYPE_ACHIEVEMENT], $types('achievements'));
        $this->assertSame([NewsEvent::TYPE_TOP_CHANGE], $types('top'));
        $this->assertSame([NewsEvent::TYPE_VERSUS_NEWS], $types('versus_news'));
        $this->assertSame([], $types('votes'));
        $this->assertSame([NewsEvent::TYPE_STATUS], $types('statuses', NewsFeedService::SOURCE_FOLLOWING));
        $this->assertSame([], $types('versus_news', NewsFeedService::SOURCE_FOLLOWING));
    }

    public function test_top_votes_and_results_stay_separate(): void
    {
        $challenge = Challenge::factory()->closed()->withOriginal()->create();
        $entry = ChallengeEntry::factory()->for($challenge)->for($this->friend)->create(['votes_count' => 4]);
        FeedPost::create(['type' => FeedPost::TYPE_TOP_CHANGE, 'user_id' => $this->friend->id, 'challenge_id' => $challenge->id, 'entry_id' => $entry->id, 'data' => ['from' => 6, 'to' => 3], 'published_at' => now()]);

        $top = $this->feed(filter: 'top')->sole();
        $this->assertSame(__('news.top_up', ['from' => 6, 'to' => 3]), $top->action());
        $this->assertStringContainsString('entry='.$entry->id, $top->url());

        $this->assertSame([NewsEvent::TYPE_RESULTS], $this->feed(filter: 'results')->pluck('type')->all());
    }

    public function test_results_show_only_the_top_three(): void
    {
        $challenge = Challenge::factory()->closed()->withOriginal()->create();
        foreach ([5, 9, 1, 7] as $votes) {
            ChallengeEntry::factory()->for($challenge)->create(['votes_count' => $votes]);
        }

        $result = $this->feed(filter: 'results')->sole();

        $this->assertNull($result->actor);
        $this->assertSame([9, 7, 5], array_map(fn (ChallengeEntry $e) => $e->votes_count, $result->podium));
    }

    public function test_hidden_challenges_and_future_news_stay_out(): void
    {
        Challenge::factory()->for($this->friend)->processing()->create();
        Challenge::factory()->for($this->friend)->failed()->create();
        FeedPost::create(['type' => FeedPost::TYPE_VERSUS_NEWS, 'title' => 'Later', 'published_at' => now()->addDay()]);

        $this->assertCount(0, $this->feed());
    }

    public function test_page_renders_challenge_rows_with_thumbnails_and_compact_rows_without(): void
    {
        Challenge::factory()->for($this->friend)->withOriginal()->create(['title' => 'Hundred pushups', 'rules' => 'Long rules #fit']);
        FeedPost::create(['type' => FeedPost::TYPE_STATUS, 'user_id' => $this->friend->id, 'body' => 'Move more, live more', 'published_at' => now()->subHour()]);
        FeedPost::create(['type' => FeedPost::TYPE_ACHIEVEMENT, 'user_id' => $this->friend->id, 'key' => 'votes_100', 'published_at' => now()->subHours(2)]);
        FeedPost::create(['type' => FeedPost::TYPE_VERSUS_NEWS, 'title' => 'New Challenge categories', 'published_at' => now()->subDay()]);

        $html = Livewire::actingAs($this->viewer)->test(NewsFeed::class)
            ->assertSee('Hundred pushups')
            ->assertSee(__('news.event_created'))
            ->assertSee('Move more, live more')
            ->assertSee(trans_choice('news.achievement_votes', 100, ['count' => 100]))
            ->assertSee('New Challenge categories')
            ->assertDontSee('#fit')
            ->html();

        preg_match_all('/data-news-event="(\w+)".*?(?=data-news-event=|$)/s', $html, $rows);
        foreach ($rows[0] as $i => $row) {
            $type = $rows[1][$i];
            $hasThumb = str_contains($row, 'data-thumb');
            $this->assertSame($type === NewsEvent::TYPE_CREATED, $hasThumb, $type);
        }
        $this->assertStringContainsString('data-versus-avatar', $html);
    }

    public function test_filters_validate_and_reset_paging(): void
    {
        Livewire::actingAs($this->viewer)->test(NewsFeed::class)
            ->set('pages', 3)
            ->set('filter', 'statuses')
            ->assertSet('pages', 1)
            ->set('filter', 'bogus')
            ->assertSet('filter', '')
            ->set('source', 'everyone-ever')
            ->assertSet('source', NewsFeedService::SOURCE_ALL);
    }
}

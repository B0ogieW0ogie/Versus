<?php

namespace Tests\Feature\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeView;
use App\Models\User;
use App\Services\Challenges\ChallengeCarousel;
use App\Services\Challenges\ChallengeFeedQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarouselAndFeedQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_seven_by_votes_then_three_low_impression_responses(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $leaders = collect(range(1, 7))->map(fn (int $i) => ChallengeEntry::factory()->for($challenge)->create([
            'votes_count' => 100 - $i,
            'impressions_count' => 1000,
        ]));
        $unseen = collect(range(1, 3))->map(fn () => ChallengeEntry::factory()->for($challenge)->create([
            'votes_count' => 0,
            'impressions_count' => 0,
        ]));
        // Pool for the 3 rotating slots is the 6 lowest-impression non-leaders: 3 unseen + 3 of these.
        $seen = ChallengeEntry::factory()->for($challenge)->count(3)->create(['votes_count' => 0, 'impressions_count' => 500]);
        // Never in the pool: more impressions than the 6 lowest.
        $overexposed = ChallengeEntry::factory()->for($challenge)->create(['votes_count' => 0, 'impressions_count' => 9000]);
        ChallengeEntry::factory()->for($challenge)->processing()->create();

        $list = app(ChallengeCarousel::class)->responses($challenge);

        $this->assertSame($leaders->pluck('id')->all(), $list->take(7)->pluck('id')->all());
        $this->assertCount(10, $list);
        $this->assertCount(10, $list->pluck('id')->unique());
        $this->assertFalse($list->contains('is_original', true));
        $pool = $unseen->pluck('id')->merge($seen->pluck('id'));
        $this->assertSame([], $list->slice(7)->pluck('id')->diff($pool)->values()->all());
        $this->assertFalse($list->contains('id', $overexposed->id));
    }

    public function test_fewer_than_ten_responses_returns_all_ready(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        ChallengeEntry::factory()->for($challenge)->count(4)->create();

        $this->assertCount(4, app(ChallengeCarousel::class)->responses($challenge));
    }

    public function test_focus_entry_outside_the_carousel_is_prepended(): void
    {
        config(['versus.challenges.carousel_top_by_votes' => 1, 'versus.challenges.carousel_fresh_slots' => 0]);
        $challenge = Challenge::factory()->withOriginal()->create();
        ChallengeEntry::factory()->for($challenge)->create(['votes_count' => 5]);
        $focus = ChallengeEntry::factory()->for($challenge)->create(['votes_count' => 0]);

        $list = app(ChallengeCarousel::class)->responses($challenge, $focus->id);

        $this->assertSame($focus->id, $list->first()->id);
        $this->assertCount(2, $list);
    }

    public function test_feed_shows_open_challenges_unseen_first_then_by_score(): void
    {
        $viewer = User::factory()->create();
        $seenTop = Challenge::factory()->create(['feed_score' => 9]);
        $unseenLow = Challenge::factory()->create(['feed_score' => 1]);
        $unseenHigh = Challenge::factory()->create(['feed_score' => 5]);
        Challenge::factory()->closed()->create(['feed_score' => 99]);
        Challenge::factory()->create(['feed_score' => 99, 'ends_at' => now()->subMinute()]);
        ChallengeView::create(['user_id' => $viewer->id, 'challenge_id' => $seenTop->id, 'viewed_at' => now()]);

        $ids = app(ChallengeFeedQuery::class)->next($viewer, [], 10)->pluck('id')->all();

        $this->assertSame([$unseenHigh->id, $unseenLow->id, $seenTop->id], $ids);
    }

    public function test_feed_excludes_already_loaded_and_uses_guest_views(): void
    {
        $a = Challenge::factory()->create(['feed_score' => 9]);
        $b = Challenge::factory()->create(['feed_score' => 5]);
        $c = Challenge::factory()->create(['feed_score' => 1]);

        $ids = app(ChallengeFeedQuery::class)->next(null, [$b->id], 10, [$a->id])->pluck('id')->all();

        $this->assertSame([$c->id, $a->id], $ids);
    }
}

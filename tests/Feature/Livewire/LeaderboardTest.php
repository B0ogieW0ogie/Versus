<?php

namespace Tests\Feature\Livewire;

use App\Actions\Comments\LikeCommentAction;
use App\Livewire\Leaderboard;
use App\Models\Battle;
use App\Models\Comment;
use App\Models\User;
use App\Models\Vote;
use App\Support\LeaderboardTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_creators_tab_ranks_by_one_percent_of_settled_pools(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Battle::factory()->settled(Battle::SIDE_A)->create([
            'created_by_id' => $alice->id,
            'total_pool' => 1000,
            'settled_at' => now(),
        ]);
        Battle::factory()->settled(Battle::SIDE_A)->create([
            'created_by_id' => $bob->id,
            'total_pool' => 500,
            'settled_at' => now(),
        ]);

        Livewire::test(Leaderboard::class)
            ->set('tab', LeaderboardTable::TAB_CREATORS)
            ->assertViewHas('rows', function ($rows) use ($alice) {
                return (int) $rows->first()->id === $alice->id
                    && (float) $rows->first()->metric_value === 10.0
                    && (float) $rows->last()->metric_value === 5.0;
            });
    }

    public function test_oracles_tab_requires_minimum_ten_votes_and_sorts_by_win_rate(): void
    {
        $sharp = User::factory()->create();
        $casual = User::factory()->create();

        $battle = Battle::factory()->settled(Battle::SIDE_A)->create();

        foreach (range(1, 9) as $_) {
            Vote::factory()->create([
                'user_id' => $casual->id,
                'battle_id' => $battle->id,
                'side' => 'A',
                'amount' => 10,
                'weight' => 10,
            ]);
        }

        foreach (range(1, 10) as $i) {
            Vote::factory()->create([
                'user_id' => $sharp->id,
                'battle_id' => $battle->id,
                'side' => $i <= 8 ? 'A' : 'B',
                'amount' => 10,
                'weight' => 10,
            ]);
        }

        Livewire::test(Leaderboard::class)
            ->set('tab', LeaderboardTable::TAB_ORACLES)
            ->assertViewHas('rows', function ($rows) use ($sharp, $casual) {
                return $rows->count() === 1
                    && (int) $rows->first()->id === $sharp->id
                    && (float) $rows->first()->metric_value === 80.0
                    && ! $rows->contains('id', $casual->id);
            });
    }

    public function test_influencers_tab_ranks_by_comment_like_income(): void
    {
        $author = User::factory()->create(['balance' => 20]);
        $voter = User::factory()->create(['balance' => 5]);

        $battle = Battle::factory()->create([
            'status' => Battle::STATUS_ACTIVE,
            'closes_at' => now()->addDay(),
        ]);

        $comment = Comment::factory()->create([
            'user_id' => $author->id,
            'battle_id' => $battle->id,
            'side' => Battle::SIDE_A,
        ]);

        app(LikeCommentAction::class)($voter, $comment, $battle);
        app(LikeCommentAction::class)(User::factory()->create(['balance' => 5]), $comment, $battle);

        Livewire::test(Leaderboard::class)
            ->set('tab', LeaderboardTable::TAB_INFLUENCERS)
            ->assertViewHas('rows', function ($rows) use ($author) {
                return (int) $rows->first()->id === $author->id
                    && (float) $rows->first()->metric_value === 2.0
                    && (int) $rows->first()->argument_votes === 2;
            });
    }

    public function test_week_period_excludes_older_settled_battles_for_creators(): void
    {
        $user = User::factory()->create();

        Battle::factory()->settled(Battle::SIDE_A)->create([
            'created_by_id' => $user->id,
            'total_pool' => 1000,
            'settled_at' => now()->subDays(10),
        ]);

        Livewire::test(Leaderboard::class)
            ->set('tab', LeaderboardTable::TAB_CREATORS)
            ->set('period', LeaderboardTable::PERIOD_WEEK)
            ->assertViewHas('rows', fn ($rows) => $rows->isEmpty());
    }

    public function test_authed_user_gets_pinned_position_with_rank_delta(): void
    {
        $leader = User::factory()->create();
        $user = User::factory()->create();

        Battle::factory()->settled(Battle::SIDE_A)->create([
            'created_by_id' => $leader->id,
            'total_pool' => 5000,
            'settled_at' => now()->subDays(10),
        ]);
        Battle::factory()->settled(Battle::SIDE_A)->create([
            'created_by_id' => $user->id,
            'total_pool' => 1000,
            'settled_at' => now()->subDays(10),
        ]);
        Battle::factory()->settled(Battle::SIDE_A)->create([
            'created_by_id' => $user->id,
            'total_pool' => 10000,
            'settled_at' => now()->subDays(3),
        ]);

        Livewire::actingAs($user)
            ->test(Leaderboard::class)
            ->set('tab', LeaderboardTable::TAB_CREATORS)
            ->set('period', LeaderboardTable::PERIOD_WEEK)
            ->assertViewHas('me', fn ($me) => $me !== null
                && $me['rank'] === 1
                && (float) $me['metric_value'] === 100.0
                && $me['delta'] === 1);
    }

    public function test_guest_has_no_pinned_position(): void
    {
        Livewire::test(Leaderboard::class)->assertViewHas('me', null);
    }

    public function test_page_renders_tabs_and_period_chips(): void
    {
        $this->get(route('leaderboard'))
            ->assertOk()
            ->assertSee(__('leaderboard.tabs.creators'), false)
            ->assertSee(__('leaderboard.periods.week'), false);
    }

    public function test_rank_badge_uses_medals_for_top_three(): void
    {
        $this->assertSame('#1 🥇', LeaderboardTable::rankBadge(1));
        $this->assertSame('#4', LeaderboardTable::rankBadge(4));
    }
}

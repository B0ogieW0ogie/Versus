<?php

namespace Tests\Feature\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\User;
use App\Services\Recommendations\RecommendedProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendedProfilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_top_creators_by_category_and_skips_the_viewer(): void
    {
        $sports = Challenge::factory()->create(['category' => Challenge::CATEGORY_SPORTS]);
        $music = Challenge::factory()->create(['category' => Challenge::CATEGORY_MUSIC]);
        $star = User::factory()->create();
        $runnerUp = User::factory()->create();
        $viewer = User::factory()->create();
        ChallengeEntry::factory()->for($sports)->for($runnerUp)->create(['votes_count' => 2]);
        ChallengeEntry::factory()->for($sports)->for($star)->create(['votes_count' => 5, 'likes_count' => 3]);
        ChallengeEntry::factory()->for($sports)->for($viewer)->create(['votes_count' => 50]);
        ChallengeEntry::factory()->for($music)->for($runnerUp)->create();

        $groups = app(RecommendedProfiles::class)->groups($viewer);

        $this->assertSame([Challenge::CATEGORY_SPORTS, Challenge::CATEGORY_MUSIC], array_column($groups, 'category'));
        $this->assertSame([$star->id, $runnerUp->id], array_map(fn (User $u) => $u->id, $groups[0]['users']));
    }
}

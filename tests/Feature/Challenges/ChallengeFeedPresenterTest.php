<?php

namespace Tests\Feature\Challenges;

use App\Actions\Challenges\CastChallengeVoteAction;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeParticipant;
use App\Models\EntryLike;
use App\Models\User;
use App\Services\Challenges\ChallengeFeedPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChallengeFeedPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_original_first_and_no_personal_state(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $response = ChallengeEntry::factory()->for($challenge)->create();

        $data = app(ChallengeFeedPresenter::class)->present($challenge, null);

        $this->assertTrue($data['slides'][0]['is_original']);
        $this->assertSame($response->id, $data['slides'][1]['entry_id']);
        $this->assertTrue($data['is_open']);
        $this->assertFalse($data['is_own']);
        $this->assertNull($data['my_vote_entry_id']);
        $this->assertSame(route('challenges.show', ['challenge' => $challenge->slug, 'entry' => $response->id]), $data['slides'][1]['share_url']);
    }

    public function test_viewer_state_vote_like_accept_and_own_entry(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $viewer = User::factory()->create();
        $mine = ChallengeEntry::factory()->for($challenge)->for($viewer)->create();
        $other = ChallengeEntry::factory()->for($challenge)->create();
        ChallengeParticipant::create(['user_id' => $viewer->id, 'challenge_id' => $challenge->id, 'accepted_at' => now()]);
        app(CastChallengeVoteAction::class)($viewer, $other);
        EntryLike::create(['user_id' => $viewer->id, 'entry_id' => $other->id]);

        $data = app(ChallengeFeedPresenter::class)->present($challenge, $viewer);
        $slides = collect($data['slides'])->keyBy('entry_id');

        $this->assertTrue($data['accepted']);
        $this->assertSame($mine->id, $data['my_entry_id']);
        $this->assertSame($other->id, $data['my_vote_entry_id']);
        $this->assertTrue($slides[$mine->id]['is_mine']);
        $this->assertTrue($slides[$other->id]['liked']);
    }

    public function test_closed_challenge_exposes_winner_and_focus_index(): void
    {
        $challenge = Challenge::factory()->closed()->withOriginal()->create();
        $winner = ChallengeEntry::factory()->for($challenge)->create(['votes_count' => 3]);
        $challenge->update(['winner_entry_id' => $winner->id]);

        $data = app(ChallengeFeedPresenter::class)->present($challenge->fresh(), null, $winner->id);

        $this->assertFalse($data['is_open']);
        $this->assertSame($winner->user->name, $data['winner_name']);
        $this->assertSame(1, $data['focus_index']);
        $this->assertTrue($data['slides'][1]['is_winner']);
    }
}

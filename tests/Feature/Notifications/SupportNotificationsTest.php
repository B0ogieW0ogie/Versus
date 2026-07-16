<?php

namespace Tests\Feature\Notifications;

use App\Actions\Comments\PostCommentAction;
use App\Actions\Comments\SupportCommentAction;
use App\Models\Battle;
use App\Models\Comment;
use App\Models\User;
use App\Models\Vote;
use App\Notifications\ArgumentSupported;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SupportNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeComment(User $author, Battle $battle): Comment
    {
        return app(PostCommentAction::class)($author, $battle, 'my argument', Battle::SIDE_A);
    }

    private function support(User $user, Battle $battle, Comment $comment, float $amount = 100): void
    {
        app(SupportCommentAction::class)($user, $battle, $comment, $amount);
    }

    public function test_first_support_notifies_the_comment_author(): void
    {
        Notification::fake();

        $author = User::factory()->create(['balance' => 1000]);
        $supporter = User::factory()->create(['balance' => 1000, 'name' => 'Bob']);
        $battle = Battle::factory()->create();
        $comment = $this->makeComment($author, $battle);

        $this->support($supporter, $battle, $comment);

        Notification::assertSentTo($author, ArgumentSupported::class, function (ArgumentSupported $n) use ($author, $comment, $supporter) {
            $data = $n->toDatabase($author);

            return $data['comment_id'] === $comment->id
                && $data['actor_name'] === 'Bob'
                && $data['actor_id'] === $supporter->id;
        });
    }

    public function test_repeat_support_by_the_same_user_notifies_only_once(): void
    {
        $author = User::factory()->create(['balance' => 1000]);
        $supporter = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();
        $comment = $this->makeComment($author, $battle);

        $this->support($supporter, $battle, $comment);
        $this->support($supporter, $battle, $comment);
        $this->support($supporter, $battle, $comment);

        $this->assertSame(1, $author->notifications()->count());
    }

    public function test_a_different_supporter_notifies_again(): void
    {
        $author = User::factory()->create(['balance' => 1000]);
        $first = User::factory()->create(['balance' => 1000]);
        $second = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();
        $comment = $this->makeComment($author, $battle);

        $this->support($first, $battle, $comment);
        $this->support($second, $battle, $comment);

        $this->assertSame(2, $author->notifications()->count());
    }

    public function test_supporting_your_own_argument_notifies_nobody(): void
    {
        Notification::fake();

        $author = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();
        $comment = $this->makeComment($author, $battle);

        $this->support($author, $battle, $comment);

        Notification::assertNothingSent();
    }

    public function test_support_still_casts_the_vote(): void
    {
        $author = User::factory()->create(['balance' => 1000]);
        $supporter = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();
        $comment = $this->makeComment($author, $battle);

        $this->support($supporter, $battle, $comment, 250);

        $this->assertSame(750.0, (float) $supporter->fresh()->balance);
        $vote = Vote::where('user_id', $supporter->id)->where('battle_id', $battle->id)->firstOrFail();
        $this->assertSame(Battle::SIDE_A, $vote->side);
        $this->assertSame(250.0, (float) $vote->amount);
    }
}

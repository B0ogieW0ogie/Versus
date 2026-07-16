<?php

namespace Tests\Feature\Notifications;

use App\Actions\Comments\LikeCommentAction;
use App\Actions\Comments\PostCommentAction;
use App\Models\Battle;
use App\Models\Comment;
use App\Models\User;
use App\Notifications\CommentLiked;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LikeNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeComment(User $author, Battle $battle): Comment
    {
        return app(PostCommentAction::class)($author, $battle, 'nice take', Battle::SIDE_A);
    }

    public function test_first_like_notifies_comment_author(): void
    {
        Notification::fake();

        $author = User::factory()->create(['balance' => 100]);
        $liker = User::factory()->create(['balance' => 100]);
        $battle = Battle::factory()->create();
        $comment = $this->makeComment($author, $battle);

        app(LikeCommentAction::class)($liker, $comment, $battle);

        Notification::assertSentTo($author, CommentLiked::class, function (CommentLiked $n) use ($author, $comment, $liker) {
            $data = $n->toDatabase($author);

            return $data['comment_id'] === $comment->id && $data['actor_name'] === $liker->name;
        });
    }

    public function test_repeat_like_does_not_notify_again(): void
    {
        Notification::fake();

        $author = User::factory()->create(['balance' => 100]);
        $liker = User::factory()->create(['balance' => 100]);
        $battle = Battle::factory()->create();
        $comment = $this->makeComment($author, $battle);

        app(LikeCommentAction::class)($liker, $comment, $battle);
        app(LikeCommentAction::class)($liker, $comment, $battle);

        Notification::assertSentToTimes($author, CommentLiked::class, 1);
    }

    public function test_self_like_is_rejected_and_does_not_notify(): void
    {
        Notification::fake();

        $author = User::factory()->create(['balance' => 100]);
        $battle = Battle::factory()->create();
        $comment = $this->makeComment($author, $battle);

        try {
            app(LikeCommentAction::class)($author, $comment, $battle);
            $this->fail('Expected ValidationException for self-like.');
        } catch (ValidationException) {
            // self-like is rejected by a pre-existing guard inside the action
        }

        Notification::assertNothingSent();
    }
}

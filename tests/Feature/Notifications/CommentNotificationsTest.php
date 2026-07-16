<?php

namespace Tests\Feature\Notifications;

use App\Actions\Comments\PostCommentAction;
use App\Models\Battle;
use App\Models\User;
use App\Notifications\CommentReplied;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CommentNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reply_notifies_parent_comment_author(): void
    {
        Notification::fake();

        $author = User::factory()->create();
        $replier = User::factory()->create();
        $battle = Battle::factory()->create();

        $parent = app(PostCommentAction::class)($author, $battle, 'root', Battle::SIDE_A);
        $reply = app(PostCommentAction::class)($replier, $battle, 'reply', null, $parent);

        Notification::assertSentTo($author, CommentReplied::class, function (CommentReplied $n) use ($author, $reply, $replier) {
            $data = $n->toDatabase($author);

            return $data['comment_id'] === $reply->id && $data['actor_name'] === $replier->name;
        });
    }

    public function test_self_reply_notifies_nobody(): void
    {
        Notification::fake();

        $author = User::factory()->create();
        $battle = Battle::factory()->create();

        $parent = app(PostCommentAction::class)($author, $battle, 'root', Battle::SIDE_A);
        app(PostCommentAction::class)($author, $battle, 'self reply', null, $parent);

        Notification::assertNothingSent();
    }

    public function test_reply_to_user_distinct_from_parent_author_is_also_notified(): void
    {
        Notification::fake();

        $author = User::factory()->create();
        $mentioned = User::factory()->create();
        $replier = User::factory()->create();
        $battle = Battle::factory()->create();

        $parent = app(PostCommentAction::class)($author, $battle, 'root', Battle::SIDE_A);
        app(PostCommentAction::class)($replier, $battle, 'reply', null, $parent, $mentioned);

        Notification::assertSentTo($author, CommentReplied::class);
        Notification::assertSentTo($mentioned, CommentReplied::class);
    }

    public function test_top_level_comment_notifies_nobody(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $battle = Battle::factory()->create();

        app(PostCommentAction::class)($user, $battle, 'top level', Battle::SIDE_A);

        Notification::assertNothingSent();
    }
}

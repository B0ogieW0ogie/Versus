<?php

namespace Tests\Feature\Notifications;

use App\Models\Battle;
use App\Models\Comment;
use App\Models\User;
use App\Notifications\BattleSettled;
use App\Notifications\CommentLiked;
use App\Notifications\CommentReplied;
use App\Notifications\ReferralPayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_battle_settled_payload_is_stored_in_database(): void
    {
        $user = User::factory()->create();
        $battle = Battle::factory()->create();

        $user->notify(new BattleSettled($battle, BattleSettled::RESULT_WON, 110.5));

        $notification = $user->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame([
            'battle_id' => $battle->id,
            'battle_slug' => $battle->slug,
            'battle_title' => $battle->title,
            'result' => 'won',
            'amount' => 110.5,
        ], $notification->data);
    }

    public function test_referral_payout_payload(): void
    {
        $user = User::factory()->create();
        $battle = Battle::factory()->create();

        $user->notify(new ReferralPayout($battle, 'Alice', 11.05));

        $this->assertSame([
            'battle_id' => $battle->id,
            'battle_slug' => $battle->slug,
            'battle_title' => $battle->title,
            'referee_name' => 'Alice',
            'amount' => 11.05,
        ], $user->notifications()->first()->data);
    }

    public function test_comment_replied_and_liked_payloads(): void
    {
        $author = User::factory()->create();
        $actor = User::factory()->create(['name' => 'Bob']);
        $battle = Battle::factory()->create();
        $comment = Comment::create([
            'user_id' => $author->id,
            'battle_id' => $battle->id,
            'body' => 'hello',
            'side' => Battle::SIDE_A,
        ]);

        $author->notify(new CommentReplied($battle, $comment, $actor));
        $author->notify(new CommentLiked($battle, $comment, $actor));

        $expected = [
            'battle_id' => $battle->id,
            'battle_slug' => $battle->slug,
            'battle_title' => $battle->title,
            'comment_id' => $comment->id,
            'actor_name' => 'Bob',
        ];
        $payloads = $author->notifications()->pluck('data');
        $this->assertCount(2, $payloads);
        $this->assertSame($expected, $payloads[0]);
        $this->assertSame($expected, $payloads[1]);
    }
}

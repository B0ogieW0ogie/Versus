<?php

namespace Tests\Feature\Livewire;

use App\Livewire\NotificationBell;
use App\Models\Battle;
use App\Models\Comment;
use App\Models\User;
use App\Notifications\ArgumentSupported;
use App\Notifications\BattleLastShot;
use App\Notifications\BattleSettled;
use App\Notifications\CommentLiked;
use App\Notifications\CommentReplied;
use App\Notifications\ReferralPayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    private function notify(User $user, float $amount = 44.0): Battle
    {
        $battle = Battle::factory()->create();
        $user->notify(new BattleSettled($battle, BattleSettled::RESULT_WON, $amount));

        return $battle;
    }

    public function test_badge_shows_unread_count(): void
    {
        $user = User::factory()->create();
        $this->notify($user);
        $this->notify($user);

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->assertSet('unreadCount', 2);
    }

    public function test_opening_dropdown_marks_all_read_and_lists_messages(): void
    {
        $user = User::factory()->create();
        $battle = $this->notify($user);

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->call('toggle')
            ->assertSet('open', true)
            ->assertSet('unreadCount', 0)
            ->assertSee($battle->title);

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_refresh_count_dispatches_ding_when_count_grows(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(NotificationBell::class);

        $this->notify($user);

        $component->call('refreshCount')
            ->assertSet('unreadCount', 1)
            ->assertDispatched('notification-ding');

        $component->call('refreshCount')
            ->assertNotDispatched('notification-ding');
    }

    public function test_bell_rendered_for_auth_users_only(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertSeeLivewire(NotificationBell::class);
        auth()->logout();
        $this->get('/')->assertDontSeeLivewire(NotificationBell::class);
    }

    public function test_dropdown_renders_all_notification_types_with_expected_text(): void
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

        $author->notify(new BattleSettled($battle, BattleSettled::RESULT_WON, 42.0));
        $author->notify(new ReferralPayout($battle, 'Alice', 4.2));
        $author->notify(new CommentReplied($battle, $comment, $actor));
        $author->notify(new CommentLiked($battle, $comment, $actor));
        $author->notify(new ArgumentSupported($battle, $comment, $actor));
        $author->notify(new BattleLastShot($battle));

        Livewire::actingAs($author)
            ->test(NotificationBell::class)
            ->call('toggle')
            ->assertSee('LAST SHOT in "'.$battle->title.'"')
            ->assertSee('You won 42.00 tokens')
            ->assertSee('Referral bonus: 4.20 tokens from Alice')
            ->assertSee('Bob replied to your comment')
            ->assertSee('Bob liked your comment')
            ->assertSee('Bob agreed with your argument')
            ->assertSee('#comment-'.$comment->id, false);
    }

    public function test_badge_caps_display_at_99_plus(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 105; $i++) {
            $this->notify($user);
        }

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->assertSet('unreadCount', 105)
            ->assertSee('99+');
    }
}

<?php

namespace Tests\Feature\Livewire;

use App\Livewire\NotificationBell;
use App\Models\Battle;
use App\Models\User;
use App\Notifications\BattleSettled;
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
}

<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BottomNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_bottom_nav_is_rendered_on_home(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(__('nav.home'))
            ->assertSee(__('nav.leaderboard'))
            ->assertSee(__('nav.my_bets'))
            ->assertSee(__('nav.profile'));
    }

    public function test_guest_clicking_my_bets_is_redirected_to_login(): void
    {
        $this->get('/my-bets')->assertRedirect('/login');
    }

    public function test_authed_user_can_reach_my_bets(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/my-bets')->assertOk();
    }
}

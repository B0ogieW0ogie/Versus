<?php

namespace Tests\Feature\Navigation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BottomNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_renders_five_bottom_nav_tabs(): void
    {
        $response = $this->get(route('home'))->assertOk();

        $response->assertSee(__('nav.home'), false);
        $response->assertSee(__('nav.feed'), false);
        $response->assertSee(__('nav.create'), false);
        $response->assertSee(__('nav.leaderboard'), false);
        $response->assertSee(__('nav.profile'), false);
    }

    public function test_guest_bottom_nav_has_no_disabled_tabs(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertSame(0, substr_count($html, 'aria-disabled="true"'));
    }

    public function test_create_fab_links_guest_to_login(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $login = preg_quote(route('login'), '#');
        $this->assertMatchesRegularExpression(
            '#<a href="'.$login.'"\s+class="-mt-7 h-14 w-14#',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '#<a href="'.preg_quote(route('battles.create'), '#').'"\s+class="-mt-7 h-14 w-14#',
            $html
        );
    }

    public function test_create_fab_links_authenticated_user_to_battles_create(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('home'))->assertOk()->getContent();

        $create = preg_quote(route('battles.create'), '#');
        $this->assertMatchesRegularExpression(
            '#<a href="'.$create.'"\s+class="-mt-7 h-14 w-14#',
            $html
        );
    }

    public function test_my_bets_tab_is_not_in_bottom_nav(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('My Bets', $html);
    }
}

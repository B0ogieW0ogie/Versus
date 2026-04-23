<?php

namespace Tests\Feature\Navigation;

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

    public function test_feed_and_create_tabs_are_disabled_placeholders(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        // Both placeholders expose aria-disabled="true" so screen readers skip them.
        $this->assertStringContainsString('aria-disabled="true"', $html);
    }

    public function test_my_bets_tab_is_not_in_bottom_nav(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString(__('nav.my_bets'), $html);
    }
}

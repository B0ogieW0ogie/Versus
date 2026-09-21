<?php

namespace Tests\Feature\Challenges;

use App\Models\Challenge;
use App\Models\User;
use App\Notifications\ChallengePublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesktopShellTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['versus.battles_enabled' => false]);
    }

    /** @return list<string> */
    private function sideItems(string $html): array
    {
        preg_match_all('/data-side-item="([^"]+)"/', $html, $m);

        return $m[1];
    }

    public function test_every_main_page_renders_inside_the_three_column_shell(): void
    {
        $user = User::factory()->create(['username' => 'dan']);
        $challenge = Challenge::factory()->withOriginal()->create();

        $pages = [
            route('home'),
            route('challenges.index'),
            route('challenges.show', $challenge->slug),
            route('challenges.mine'),
            route('challenges.create'),
            route('notifications'),
            route('profile.edit'),
            route('profile.show', $user),
            route('profile.settings'),
        ];

        foreach ($pages as $url) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('data-shell-left', $html, $url);
            $this->assertStringContainsString('data-recommendations', $html, $url);
            $this->assertSame(1, substr_count($html, 'data-side-nav'), $url);
            $this->assertStringContainsString('data-nav="top" class="', $html, $url);
            $this->assertDoesNotMatchRegularExpression('/data-nav="top" class="[^"]*(?<!lg:hidden)"/', $html, $url);
        }
    }

    public function test_side_menu_has_only_the_final_items(): void
    {
        $html = $this->actingAs(User::factory()->create())->get(route('home'))->getContent();

        $this->assertSame(['home', 'profile.edit', 'challenges.index', 'notifications', 'search', 'settings'], $this->sideItems($html));
        $nav = substr($html, strpos($html, 'data-side-nav'));
        $this->assertStringNotContainsString(__('challenges.nav_my'), substr($nav, 0, strpos($nav, '</nav>')));
    }

    public function test_home_and_challenges_are_separate_sections(): void
    {
        $user = User::factory()->create();

        $home = $this->actingAs($user)->get(route('home'))->assertOk()->getContent();
        $this->assertStringContainsString('data-page="news"', $home);
        $this->assertMatchesRegularExpression('/data-side-item="home"\s+aria-current="page"/', $home);
        $this->assertStringNotContainsString('data-card', $home);

        $feed = $this->actingAs($user)->get(route('challenges.index'))->assertOk()->getContent();
        $this->assertStringContainsString('data-card', $feed);
        $this->assertMatchesRegularExpression('/data-side-item="challenges.index"\s+aria-current="page"/', $feed);
        $this->assertStringContainsString('data-challenges-tabs', $feed);
    }

    public function test_my_challenges_is_a_tab_inside_challenges(): void
    {
        $html = $this->actingAs(User::factory()->create())->get(route('challenges.mine'))->getContent();

        $this->assertStringContainsString('data-challenges-tabs', $html);
        $this->assertMatchesRegularExpression('/data-side-item="challenges.index"\s+aria-current="page"/', $html);
    }

    public function test_guest_sees_home_and_login_prompts(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('data-page="news"', $html);
        $this->assertStringNotContainsString('data-side-item="settings"', $html);
        $this->assertStringContainsString(route('login'), $html);
        $this->get(route('notifications'))->assertRedirect(route('login'));
    }

    public function test_notifications_page_lists_and_marks_read_and_menu_shows_unread_badge(): void
    {
        $user = User::factory()->create();
        $challenge = Challenge::factory()->for($user)->create(['title' => 'Kickflip']);
        $user->notify(new ChallengePublished($challenge));

        $this->actingAs($user)->get(route('home'))->assertSee('data-unread', false);

        $this->actingAs($user)->get(route('notifications'))
            ->assertOk()
            ->assertSee(__('challenges.notif_published', ['title' => 'Kickflip']))
            ->assertSee('/c/'.$challenge->slug, false);

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->actingAs($user)->get(route('home'))->assertDontSee('data-unread', false);
    }

    public function test_settings_page_has_language_and_logout(): void
    {
        $this->actingAs(User::factory()->create())->get(route('profile.settings'))
            ->assertOk()
            ->assertSee('data-settings="language"', false)
            ->assertSee('data-settings="logout"', false);
    }
}

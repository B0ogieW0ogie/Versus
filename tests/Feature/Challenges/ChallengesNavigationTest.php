<?php

namespace Tests\Feature\Challenges;

use App\Models\Battle;
use App\Models\Challenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChallengesNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['versus.battles_enabled' => false]);
    }

    public function test_battle_pages_redirect_to_challenges(): void
    {
        $user = User::factory()->create();
        $battle = Battle::factory()->create();

        $this->get('/')->assertRedirect(route('challenges.index'));
        $this->get(route('battles.show', $battle->slug))->assertRedirect(route('challenges.index'));
        $this->get(route('leaderboard'))->assertRedirect(route('challenges.index'));
        $this->actingAs($user)->get(route('wallet'))->assertRedirect(route('challenges.index'));
        $this->actingAs($user)->get(route('battles.create'))->assertRedirect(route('challenges.index'));
    }

    public function test_bottom_nav_shows_challenge_tabs(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('challenges.mine'))->assertOk()->getContent();

        $this->assertStringContainsString(__('challenges.nav_challenges'), $html);
        $this->assertStringContainsString(__('challenges.nav_my'), $html);
        $this->assertStringContainsString('href="'.route('challenges.create').'"', $html);
        $this->assertStringNotContainsString('href="'.route('leaderboard').'"', $html);
    }

    public function test_bottom_nav_hidden_on_the_form(): void
    {
        $user = User::factory()->create(['username' => 'dan']);
        $challenge = Challenge::factory()->withOriginal()->create();

        // The desktop header still links to My Challenges, so assert on the bottom-nav container itself.
        $bottomNav = 'fixed bottom-0 inset-x-0 z-40';
        $this->actingAs($user)->get(route('challenges.create'))->assertOk()->assertDontSee($bottomNav, false);
        $this->actingAs($user)->get(route('challenges.respond', $challenge->slug))->assertOk()->assertDontSee($bottomNav, false);
        $this->actingAs($user)->get(route('challenges.mine'))->assertOk()->assertSee($bottomNav, false);
    }

    public function test_top_nav_is_hidden_on_mobile_and_lg_on_the_feed(): void
    {
        $this->get(route('challenges.index'))
            ->assertOk()
            ->assertSee('data-nav="top" class="hidden sm:block lg:hidden"', false);

        $this->actingAs(User::factory()->create())->get(route('challenges.mine'))
            ->assertOk()
            ->assertSee('data-nav="top" class="lg:hidden"', false);

        $this->actingAs(User::factory()->create())->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('data-nav="top" class=""', false);
    }

    public function test_balance_chip_is_hidden_when_battles_are_off(): void
    {
        $chip = 'x-on:balance-updated.window="balance = $event.detail.balance"';
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('challenges.mine'))->assertOk()->assertDontSee($chip, false);

        config(['versus.battles_enabled' => true]);
        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee($chip, false);
    }

    public function test_battles_still_work_when_flag_is_on(): void
    {
        config(['versus.battles_enabled' => true]);

        $this->get('/')->assertOk();
    }
}

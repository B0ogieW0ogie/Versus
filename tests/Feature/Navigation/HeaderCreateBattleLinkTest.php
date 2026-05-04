<?php

namespace Tests\Feature\Navigation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderCreateBattleLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_does_not_see_create_battle_in_header(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString(__('nav.create_battle'), $html);
    }

    public function test_authenticated_user_sees_create_battle_in_header(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString(__('nav.create_battle'), $html);
    }

    public function test_guest_is_redirected_from_battles_create(): void
    {
        $this->get(route('battles.create'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_open_battles_create(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('battles.create'))
            ->assertOk()
            ->assertSee(__('battle.create_title'), false);
    }
}

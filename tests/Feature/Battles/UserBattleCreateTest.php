<?php

namespace Tests\Feature\Battles;

use App\Livewire\BattleCreate;
use App\Models\Battle;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserBattleCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_battle_with_expected_defaults(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $closesAt = now()->addDays(7)->startOfMinute();

        Livewire::actingAs($user)
            ->test(BattleCreate::class)
            ->set('side_a_label', 'Tabs')
            ->set('side_b_label', 'Spaces')
            ->set('category_id', (string) $category->id)
            ->set('closes_at', $closesAt->format('Y-m-d\TH:i'))
            ->call('store')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('battles', [
            'title' => 'Tabs VS Spaces',
            'description' => null,
            'status' => Battle::STATUS_ACTIVE,
            'created_by_id' => $user->id,
            'category_id' => $category->id,
            'winning_side' => null,
        ]);

        $battle = Battle::query()->where('title', 'Tabs VS Spaces')->first();
        $this->assertNotNull($battle);
        $this->assertNull($battle->side_a_subtitle);
        $this->assertNull($battle->side_b_subtitle);
        $this->assertNull($battle->opens_at);
        $this->assertNull($battle->ai_screened_at);
        $this->assertNotEmpty($battle->slug);
        $this->assertSame(0.0, (float) $battle->total_pool);
        $this->assertFalse($battle->is_sponsored);
    }

    public function test_create_requires_category(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(BattleCreate::class)
            ->set('side_a_label', 'A')
            ->set('side_b_label', 'B')
            ->set('closes_at', now()->addDay()->format('Y-m-d\TH:i'))
            ->call('store')
            ->assertHasErrors(['category_id']);
    }

    public function test_complete_ai_screening_sets_timestamp_and_redirects(): void
    {
        $user = User::factory()->create();
        $battle = Battle::factory()->create([
            'created_by_id' => $user->id,
            'ai_screened_at' => null,
        ]);

        Livewire::actingAs($user)
            ->test(BattleCreate::class)
            ->call('completeAiScreening', $battle->id)
            ->assertRedirect(route('battles.show', $battle));

        $this->assertNotNull($battle->fresh()->ai_screened_at);
    }

    public function test_complete_ai_screening_forbidden_for_non_creator(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $battle = Battle::factory()->create([
            'created_by_id' => $owner->id,
        ]);

        Livewire::actingAs($other)
            ->test(BattleCreate::class)
            ->call('completeAiScreening', $battle->id)
            ->assertForbidden();
    }

    public function test_complete_ai_screening_is_idempotent(): void
    {
        $user = User::factory()->create();
        $first = now()->subHour();
        $battle = Battle::factory()->create([
            'created_by_id' => $user->id,
            'ai_screened_at' => $first,
        ]);

        Livewire::actingAs($user)
            ->test(BattleCreate::class)
            ->call('completeAiScreening', $battle->id)
            ->assertRedirect(route('battles.show', $battle));

        $this->assertSame(
            $first->format('Y-m-d H:i:s'),
            $battle->fresh()->ai_screened_at?->format('Y-m-d H:i:s'),
        );
    }
}

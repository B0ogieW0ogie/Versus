<?php

namespace Tests\Feature\Battles;

use App\Models\Battle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattleStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_shot_battle_is_open_for_voting_despite_expired_timer(): void
    {
        $battle = Battle::factory()->create([
            'status' => Battle::STATUS_LAST_SHOT,
            'closes_at' => now()->subHour(),
        ]);

        $this->assertTrue($battle->isOpenForVoting());
    }

    public function test_void_reason_is_persisted(): void
    {
        $battle = Battle::factory()->create(['void_reason' => Battle::VOID_STOMP]);

        $this->assertSame(Battle::VOID_STOMP, $battle->fresh()->void_reason);
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\Battle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattleSponsorshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_battle_form_file_declares_sponsorship_fields(): void
    {
        $source = file_get_contents(base_path('app/Filament/Admin/Resources/Battles/Schemas/BattleForm.php'));

        $this->assertStringContainsString("Toggle::make('is_sponsored')", $source);
        $this->assertStringContainsString("TextInput::make('sponsor_handle')", $source);
        $this->assertStringNotContainsString("Toggle::make('is_featured')", $source);
        $this->assertStringContainsString("Toggle::make('deferred_start')", $source);
        $this->assertStringContainsString("Radio::make('duration_preset')", $source);
        $this->assertStringContainsString('->default(Battle::STATUS_ACTIVE)', $source);
    }

    public function test_battle_can_persist_sponsorship_fields(): void
    {
        $battle = Battle::factory()->create([
            'is_sponsored' => true,
            'sponsor_handle' => '@acme',
        ]);

        $fresh = $battle->fresh();
        $this->assertTrue($fresh->is_sponsored);
        $this->assertSame('@acme', $fresh->sponsor_handle);
    }
}

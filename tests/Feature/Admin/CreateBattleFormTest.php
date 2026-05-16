<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Resources\Battles\Pages\CreateBattle;
use App\Support\BattleDurationPreset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CreateBattleFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_mutate_form_data_sets_opens_at_to_now_without_deferred_start(): void
    {
        $page = new CreateBattle;
        $method = new \ReflectionMethod($page, 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        $fixed = Carbon::parse('2026-05-10 10:00:00');
        Carbon::setTestNow($fixed);

        try {
            $result = $method->invoke($page, [
                'deferred_start' => false,
                'duration_preset' => '24h',
                'title' => 'Test',
            ]);

            $this->assertArrayNotHasKey('deferred_start', $result);
            $this->assertArrayNotHasKey('duration_preset', $result);
            $this->assertTrue($fixed->equalTo($result['opens_at']));
            $this->assertTrue(
                BattleDurationPreset::closesAt('24h', $fixed)->equalTo($result['closes_at']),
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_mutate_form_data_keeps_scheduled_opens_at_with_deferred_start(): void
    {
        $page = new CreateBattle;
        $method = new \ReflectionMethod($page, 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);
        $scheduled = now()->addDays(2);

        $result = $method->invoke($page, [
            'deferred_start' => true,
            'opens_at' => $scheduled,
            'duration_preset' => '72h',
        ]);

        $this->assertArrayNotHasKey('deferred_start', $result);
        $this->assertArrayNotHasKey('duration_preset', $result);
        $this->assertTrue($scheduled->equalTo($result['opens_at']));
        $this->assertTrue(
            BattleDurationPreset::closesAt('72h', $scheduled)->equalTo($result['closes_at']),
        );
    }
}

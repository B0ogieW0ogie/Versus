<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Resources\Battles\Pages\CreateBattle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateBattleFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_mutate_form_data_sets_opens_at_to_now_without_deferred_start(): void
    {
        $page = new CreateBattle;
        $method = new \ReflectionMethod($page, 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        $result = $method->invoke($page, [
            'deferred_start' => false,
            'title' => 'Test',
        ]);

        $this->assertArrayNotHasKey('deferred_start', $result);
        $this->assertNotNull($result['opens_at']);
        $this->assertTrue($result['opens_at']->lessThanOrEqualTo(now()->addSecond()));
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
        ]);

        $this->assertArrayNotHasKey('deferred_start', $result);
        $this->assertTrue($scheduled->equalTo($result['opens_at']));
    }
}

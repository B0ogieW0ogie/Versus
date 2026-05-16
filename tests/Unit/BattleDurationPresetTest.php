<?php

namespace Tests\Unit;

use App\Support\BattleDurationPreset;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BattleDurationPresetTest extends TestCase
{
    public function test_closes_at_adds_preset_hours_from_opens_at(): void
    {
        $opens = Carbon::parse('2026-05-01 12:00:00');

        $closes = BattleDurationPreset::closesAt('24h', $opens);

        $this->assertTrue($closes->equalTo($opens->copy()->addHours(24)));
    }

    public function test_detect_matches_preset_from_opens_and_closes(): void
    {
        $opens = Carbon::parse('2026-05-01 12:00:00');
        $closes = $opens->copy()->addHours(9);

        $this->assertSame('9h', BattleDurationPreset::detect($opens, $closes));
    }

    public function test_detect_falls_back_to_default_for_custom_duration(): void
    {
        $opens = Carbon::parse('2026-05-01 12:00:00');
        $closes = $opens->copy()->addHours(10);

        $this->assertSame(BattleDurationPreset::DEFAULT, BattleDurationPreset::detect($opens, $closes));
    }
}

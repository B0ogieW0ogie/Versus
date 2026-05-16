<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class BattleDurationPreset
{
    /** @var list<string> */
    public const PRESETS = ['1h', '3h', '9h', '24h', '48h', '72h'];

    public const DEFAULT = '24h';

    public static function hours(string $preset): int
    {
        return match ($preset) {
            '1h' => 1,
            '3h' => 3,
            '9h' => 9,
            '24h' => 24,
            '48h' => 48,
            '72h' => 72,
            default => 24,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::PRESETS as $preset) {
            $options[$preset] = __('battle.create_duration_'.$preset);
        }

        return $options;
    }

    public static function closesAt(string $preset, Carbon $opensAt): Carbon
    {
        return $opensAt->copy()->addHours(self::hours($preset));
    }

    public static function detect(?Carbon $opensAt, ?Carbon $closesAt): string
    {
        if ($closesAt === null) {
            return self::DEFAULT;
        }

        $base = $opensAt ?? $closesAt;

        foreach (self::PRESETS as $preset) {
            $expected = self::closesAt($preset, $base);

            if (abs($expected->diffInSeconds($closesAt, false)) <= 60) {
                return $preset;
            }
        }

        return self::DEFAULT;
    }
}

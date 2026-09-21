<?php

namespace App\Services\Video;

use Illuminate\Validation\ValidationException;

/** A [start, end) cut of the source video, in milliseconds, picked in the create/respond form. */
final class Trim
{
    public const MIN_MS = 1000;

    private function __construct(public readonly int $startMs, public readonly int $endMs) {}

    /**
     * Null when the form sent no trim (keep the whole video).
     *
     * @throws ValidationException when the cut is malformed or longer than the video limit
     */
    public static function fromForm(?int $startMs, ?int $endMs): ?self
    {
        if ($startMs === null && $endMs === null) {
            return null;
        }

        $start = max(0, (int) $startMs);
        $end = (int) $endMs;
        $maxMs = (int) config('versus.challenges.max_video_seconds') * 1000 + 500;

        if ($endMs === null || $end - $start < self::MIN_MS || $end - $start > $maxMs) {
            throw ValidationException::withMessages([
                'trim' => __('challenges.trim_invalid', ['seconds' => config('versus.challenges.max_video_seconds')]),
            ]);
        }

        return new self($start, $end);
    }

    public function lengthMs(): int
    {
        return $this->endMs - $this->startMs;
    }
}

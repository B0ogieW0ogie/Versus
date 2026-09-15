<?php

namespace App\Services\Video;

final class VideoProbe
{
    public function __construct(
        public readonly bool $hasVideo,
        public readonly int $durationMs,
    ) {}
}

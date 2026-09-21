<?php

namespace App\Services\Video;

interface VideoTranscoder
{
    /** @throws VideoProcessingException */
    public function probe(string $path): VideoProbe;

    /**
     * Cuts to [startMs, endMs) when given.
     *
     * @throws VideoProcessingException
     */
    public function transcode(string $input, string $output, ?int $startMs = null, ?int $endMs = null): void;

    /** @throws VideoProcessingException */
    public function poster(string $input, string $output): void;
}

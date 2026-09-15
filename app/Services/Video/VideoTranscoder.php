<?php

namespace App\Services\Video;

interface VideoTranscoder
{
    /** @throws VideoProcessingException */
    public function probe(string $path): VideoProbe;

    /** @throws VideoProcessingException */
    public function transcode(string $input, string $output): void;

    /** @throws VideoProcessingException */
    public function poster(string $input, string $output): void;
}

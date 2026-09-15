<?php

namespace Tests\Fakes;

use App\Services\Video\VideoProbe;
use App\Services\Video\VideoProcessingException;
use App\Services\Video\VideoTranscoder;

class FakeVideoTranscoder implements VideoTranscoder
{
    public bool $hasVideo = true;

    public int $durationMs = 15000;

    public bool $failTranscode = false;

    public function probe(string $path): VideoProbe
    {
        return new VideoProbe($this->hasVideo, $this->durationMs);
    }

    public function transcode(string $input, string $output): void
    {
        if ($this->failTranscode) {
            throw VideoProcessingException::because('reason_generic');
        }

        file_put_contents($output, 'fake-mp4');
    }

    public function poster(string $input, string $output): void
    {
        file_put_contents($output, 'fake-jpg');
    }
}

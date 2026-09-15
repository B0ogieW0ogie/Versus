<?php

namespace App\Services\Video;

use RuntimeException;

/** A permanent processing failure: retrying the job will not help. */
class VideoProcessingException extends RuntimeException
{
    public function __construct(public readonly string $reasonKey)
    {
        parent::__construct($reasonKey);
    }

    public static function because(string $reasonKey): self
    {
        return new self($reasonKey);
    }
}

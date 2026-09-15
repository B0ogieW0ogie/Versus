<?php

namespace App\Notifications;

use App\Models\Challenge;

class EntryProcessingFailed extends ChallengeNotification
{
    public function __construct(Challenge $challenge, private readonly string $reasonKey)
    {
        parent::__construct($challenge);
    }

    protected function extra(): array
    {
        return ['reason' => $this->reasonKey];
    }
}

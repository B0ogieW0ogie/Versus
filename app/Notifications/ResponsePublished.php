<?php

namespace App\Notifications;

use App\Models\Challenge;
use App\Models\ChallengeEntry;

class ResponsePublished extends ChallengeNotification
{
    public function __construct(Challenge $challenge, private readonly ChallengeEntry $entry)
    {
        parent::__construct($challenge);
    }

    protected function extra(): array
    {
        return ['entry_id' => $this->entry->id];
    }
}

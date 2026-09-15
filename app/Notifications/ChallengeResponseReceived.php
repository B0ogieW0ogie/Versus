<?php

namespace App\Notifications;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\User;

class ChallengeResponseReceived extends ChallengeNotification
{
    public function __construct(Challenge $challenge, private readonly ChallengeEntry $entry, private readonly User $actor)
    {
        parent::__construct($challenge);
    }

    protected function extra(): array
    {
        return ['entry_id' => $this->entry->id, 'actor_name' => $this->actor->name];
    }
}

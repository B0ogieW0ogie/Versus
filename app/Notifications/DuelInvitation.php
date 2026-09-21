<?php

namespace App\Notifications;

use App\Models\Challenge;

/** Sent to the chosen opponent once a Duel goes live. */
class DuelInvitation extends ChallengeNotification
{
    public function __construct(Challenge $challenge, private readonly string $actorName)
    {
        parent::__construct($challenge);
    }

    /** @return array<string, mixed> */
    protected function extra(): array
    {
        return ['actor_name' => $this->actorName];
    }
}

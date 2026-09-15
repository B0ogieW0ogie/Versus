<?php

namespace App\Notifications;

use App\Models\Challenge;
use Illuminate\Notifications\Notification;

abstract class ChallengeNotification extends Notification
{
    public function __construct(protected readonly Challenge $challenge) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'challenge_id' => $this->challenge->id,
            'challenge_slug' => $this->challenge->slug,
            'challenge_title' => $this->challenge->title,
        ] + $this->extra();
    }

    /** @return array<string, mixed> */
    protected function extra(): array
    {
        return [];
    }
}

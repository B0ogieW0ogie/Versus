<?php

namespace App\Notifications;

use App\Models\Battle;
use Illuminate\Notifications\Notification;

class BattleLastShot extends Notification
{
    public function __construct(private readonly Battle $battle) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'battle_id' => $this->battle->id,
            'battle_slug' => $this->battle->slug,
            'battle_title' => $this->battle->title,
        ];
    }
}

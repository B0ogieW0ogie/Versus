<?php

namespace App\Notifications;

use App\Models\Battle;
use Illuminate\Notifications\Notification;

class BattleSettled extends Notification
{
    public const RESULT_WON = 'won';

    public const RESULT_LOST = 'lost';

    public const RESULT_REFUNDED = 'refunded';

    public function __construct(
        private readonly Battle $battle,
        private readonly string $result,
        private readonly float $amount,
    ) {}

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
            'result' => $this->result,
            'amount' => $this->amount,
        ];
    }
}

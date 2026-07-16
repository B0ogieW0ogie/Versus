<?php

namespace App\Notifications;

use App\Models\Battle;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Notifications\Notification;

class CommentLiked extends Notification
{
    public function __construct(
        private readonly Battle $battle,
        private readonly Comment $comment,
        private readonly User $actor,
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
            'comment_id' => $this->comment->id,
            'actor_name' => $this->actor->name,
        ];
    }
}

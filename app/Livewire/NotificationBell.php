<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Component;

class NotificationBell extends Component
{
    public bool $open = false;

    public int $unreadCount = 0;

    /** @var list<string> */
    public array $freshIds = [];

    public function mount(): void
    {
        $this->unreadCount = $this->user()->unreadNotifications()->count();
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;

        if ($this->open) {
            $this->freshIds = $this->user()->unreadNotifications()->pluck('id')->all();
            $this->user()->unreadNotifications()->update(['read_at' => now()]);
            $this->unreadCount = 0;
        } else {
            $this->freshIds = [];
        }
    }

    public function refreshCount(): void
    {
        $fresh = $this->user()->unreadNotifications()->count();

        if ($fresh > $this->unreadCount) {
            $this->dispatch('notification-ding');
        }

        $this->unreadCount = $fresh;
    }

    public function render(): View
    {
        $items = ! $this->open ? collect() : $this->user()
            ->notifications()
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'message' => $this->message($notification),
                'url' => $this->url($notification),
                'time' => $notification->created_at?->diffForHumans() ?? '',
                'fresh' => in_array($notification->id, $this->freshIds, true),
            ])
            ->filter(fn (array $item): bool => $item['message'] !== '')
            ->values();

        return view('livewire.notification-bell', ['items' => $items]);
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }

    private function message(DatabaseNotification $notification): string
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;
        $battle = (string) ($data['battle_title'] ?? '');

        return match (class_basename($notification->type)) {
            'BattleSettled' => __('notifications.battle_'.$data['result'], [
                'amount' => number_format((float) $data['amount'], 2),
                'battle' => $battle,
            ]),
            'ReferralPayout' => __('notifications.referral_payout', [
                'amount' => number_format((float) $data['amount'], 2),
                'name' => (string) $data['referee_name'],
                'battle' => $battle,
            ]),
            'CommentReplied' => __('notifications.comment_replied', [
                'name' => (string) $data['actor_name'],
                'battle' => $battle,
            ]),
            'CommentLiked' => __('notifications.comment_liked', [
                'name' => (string) $data['actor_name'],
                'battle' => $battle,
            ]),
            default => '',
        };
    }

    private function url(DatabaseNotification $notification): string
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;
        $url = route('battles.show', ['battle' => (string) $data['battle_slug']]);

        return isset($data['comment_id']) ? $url.'#comment-'.$data['comment_id'] : $url;
    }
}

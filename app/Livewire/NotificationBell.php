<?php

namespace App\Livewire;

use App\Models\User;
use App\Support\NotificationPresenter;
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

    public function render(NotificationPresenter $presenter): View
    {
        $items = ! $this->open ? collect() : $this->user()
            ->notifications()
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'message' => $presenter->message($notification),
                'url' => $presenter->url($notification),
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
}

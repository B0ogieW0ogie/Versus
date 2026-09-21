<?php

namespace App\Livewire;

use App\Models\User;
use App\Support\NotificationPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class NotificationsPage extends Component
{
    private const PER_PAGE = 30;

    public int $pages = 1;

    /**
     * Unread on arrival: highlighted for this visit, while the badge clears at once.
     *
     * @var list<string>
     */
    #[Locked]
    public array $freshIds = [];

    public function mount(): void
    {
        $this->freshIds = $this->user()->unreadNotifications()->pluck('id')->all();
        $this->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function loadMore(): void
    {
        $this->pages++;
    }

    public function render(NotificationPresenter $presenter): View
    {
        $limit = $this->pages * self::PER_PAGE;
        $notifications = $this->user()->notifications()->latest()->limit($limit + 1)->get();

        $items = $notifications->take($limit)
            ->map(fn (DatabaseNotification $n): array => [
                'id' => $n->id,
                'message' => $presenter->message($n),
                'url' => $presenter->url($n),
                'time' => $n->created_at?->diffForHumans() ?? '',
                'fresh' => in_array($n->id, $this->freshIds, true),
            ])
            ->filter(fn (array $item): bool => $item['message'] !== '')
            ->values();

        return view('livewire.notifications-page', [
            'items' => $items,
            'hasMore' => $notifications->count() > $limit,
        ]);
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}

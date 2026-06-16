<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\Feed\FeedService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

class FeedPage extends Component
{
    private const PER_PAGE = 15;

    public string $filter = FeedService::FILTER_ALL;

    public int $pages = 1;

    public bool $hasMore = false;

    public function setFilter(string $filter): void
    {
        $valid = [
            FeedService::FILTER_ALL,
            FeedService::FILTER_VOTES,
            FeedService::FILTER_ARGUMENTS,
            FeedService::FILTER_CREATED,
            FeedService::FILTER_RESULTS,
        ];

        if (! in_array($filter, $valid, true)) {
            return;
        }

        $this->filter = $filter;
        $this->pages = 1;
    }

    public function loadMore(): void
    {
        $this->pages++;
    }

    #[Layout('layouts.app')]
    public function render(FeedService $feed): View
    {
        /** @var User $viewer */
        $viewer = Auth::user();

        $limit = $this->pages * self::PER_PAGE;

        // Fetch one extra to know whether a "Load more" button is warranted.
        $events = $feed->events($viewer, $this->filter, null, $limit + 1);

        $this->hasMore = $events->count() > $limit;

        return view('livewire.feed-page', [
            'events' => $events->take($limit),
        ]);
    }
}

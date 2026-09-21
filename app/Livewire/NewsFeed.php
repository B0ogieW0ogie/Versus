<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\News\NewsFeedService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Home: the News Feed. The vertical Challenge video feed lives under Challenges. */
#[Layout('layouts.app')]
class NewsFeed extends Component
{
    private const PER_PAGE = 20;

    public string $source = NewsFeedService::SOURCE_ALL;

    /** '' = all events, else a key of NewsFeedService::FILTERS */
    public string $filter = '';

    public int $pages = 1;

    public function updated(string $property): void
    {
        if (! in_array($this->source, NewsFeedService::SOURCES, true)) {
            $this->source = NewsFeedService::SOURCE_ALL;
        }
        if ($this->filter !== '' && ! array_key_exists($this->filter, NewsFeedService::FILTERS)) {
            $this->filter = '';
        }
        if (in_array($property, ['source', 'filter'], true)) {
            $this->pages = 1;
        }
    }

    public function loadMore(): void
    {
        $this->pages++;
    }

    public function render(NewsFeedService $news): View
    {
        /** @var User|null $viewer */
        $viewer = Auth::user();
        $limit = $this->pages * self::PER_PAGE;
        $events = $news->events($viewer, $this->source, $this->filter !== '' ? $this->filter : null, $limit + 1);

        return view('livewire.news-feed', [
            'events' => $events->take($limit),
            'hasMore' => $events->count() > $limit,
            'sources' => NewsFeedService::SOURCES,
            'filters' => array_keys(NewsFeedService::FILTERS),
        ]);
    }
}

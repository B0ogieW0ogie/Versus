<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\News\NewsEvent;
use App\Services\News\NewsFeedService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Home: the News Feed. The vertical Challenge video feed lives under Challenges. */
#[Layout('layouts.app')]
class NewsFeed extends Component
{
    private const PER_PAGE = 15;

    public string $scope = NewsFeedService::SCOPE_ALL;

    public string $type = '';

    public int $pages = 1;

    public function updated(string $property): void
    {
        if (! in_array($this->scope, [NewsFeedService::SCOPE_ALL, NewsFeedService::SCOPE_FOLLOWING], true)) {
            $this->scope = NewsFeedService::SCOPE_ALL;
        }
        if ($this->type !== '' && ! in_array($this->type, NewsEvent::TYPES, true)) {
            $this->type = '';
        }
        if (in_array($property, ['scope', 'type'], true)) {
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
        $events = $news->events($viewer, $this->scope, $this->type !== '' ? $this->type : null, $limit + 1);

        return view('livewire.news-feed', [
            'events' => $events->take($limit),
            'hasMore' => $events->count() > $limit,
            'types' => NewsEvent::TYPES,
        ]);
    }
}

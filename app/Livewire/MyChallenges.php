<?php

namespace App\Livewire;

use App\Actions\Challenges\LeaveChallengeAction;
use App\Models\Challenge;
use App\Models\User;
use App\Services\Challenges\MyChallengesQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class MyChallenges extends Component
{
    private const PER_PAGE = 20;

    public int $pages = 1;

    public function loadMore(): void
    {
        $this->pages++;
    }

    public function leave(int $challengeId, LeaveChallengeAction $leave): void
    {
        try {
            $leave($this->user(), Challenge::findOrFail($challengeId));
        } catch (ValidationException $e) {
            $this->dispatch('versus-stake-toast', title: (string) collect($e->errors())->flatten()->first());
        }
    }

    public function render(MyChallengesQuery $query): View
    {
        $limit = $this->pages * self::PER_PAGE;
        $cards = $query->cards($this->user(), $limit + 1);

        return view('livewire.my-challenges', [
            'cards' => array_slice($cards, 0, $limit),
            'hasMore' => count($cards) > $limit,
            'activeCount' => $query->activeCount($this->user()),
        ]);
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}

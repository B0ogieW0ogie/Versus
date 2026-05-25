<?php

namespace App\Livewire;

use App\Support\LeaderboardTable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Leaderboard extends Component
{
    #[Layout('layouts.app')]
    public string $tab = LeaderboardTable::TAB_CREATORS;

    public string $period = LeaderboardTable::PERIOD_ALL;

    public function setTab(string $tab): void
    {
        if (! in_array($tab, [
            LeaderboardTable::TAB_CREATORS,
            LeaderboardTable::TAB_ORACLES,
            LeaderboardTable::TAB_INFLUENCERS,
        ], true)) {
            return;
        }

        $this->tab = $tab;
    }

    public function setPeriod(string $period): void
    {
        if (! in_array($period, [
            LeaderboardTable::PERIOD_WEEK,
            LeaderboardTable::PERIOD_MONTH,
            LeaderboardTable::PERIOD_ALL,
        ], true)) {
            return;
        }

        $this->period = $period;
    }

    public function render(): View
    {
        $table = new LeaderboardTable;
        $rows = $table->rows($this->tab, $this->period);

        $me = null;
        if (Auth::check()) {
            $me = $table->selfRow((int) Auth::id(), $this->tab, $this->period);
        }

        return view('livewire.leaderboard', [
            'rows' => $rows,
            'me' => $me,
        ]);
    }
}

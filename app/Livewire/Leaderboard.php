<?php

namespace App\Livewire;

use App\Models\Battle;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Leaderboard extends Component
{
    #[Layout('layouts.app')]
    public function render(): View
    {
        $winningsSubquery = $this->winningsSubquery();

        $rows = User::query()
            ->select('users.id', 'users.name')
            ->leftJoinSub($winningsSubquery, 'w', fn ($j) => $j->on('w.user_id', '=', 'users.id'))
            ->selectRaw('COALESCE(w.total_winnings, 0) AS total_winnings')
            ->orderByDesc('total_winnings')
            ->orderBy('users.id')
            ->limit(100)
            ->get();

        $me = null;
        if (Auth::check() && ! $rows->contains('id', Auth::id())) {
            $me = $this->buildSelfRow($winningsSubquery);
        }

        return view('livewire.leaderboard', [
            'rows' => $rows,
            'me' => $me,
        ]);
    }

    private function winningsSubquery(): QueryBuilder
    {
        return Vote::query()
            ->join('battles', 'battles.id', '=', 'votes.battle_id')
            ->where('battles.status', Battle::STATUS_SETTLED)
            ->whereNotNull('battles.winning_side')
            ->whereColumn('votes.side', 'battles.winning_side')
            ->selectRaw('votes.user_id, COALESCE(SUM(votes.payout), 0) AS total_winnings')
            ->groupBy('votes.user_id')
            ->toBase();
    }

    /**
     * @return array{rank:int,total_winnings:float}
     */
    private function buildSelfRow(QueryBuilder $winningsSubquery): array
    {
        $mine = (float) Vote::query()
            ->join('battles', 'battles.id', '=', 'votes.battle_id')
            ->where('votes.user_id', Auth::id())
            ->where('battles.status', Battle::STATUS_SETTLED)
            ->whereNotNull('battles.winning_side')
            ->whereColumn('votes.side', 'battles.winning_side')
            ->sum('votes.payout');

        $ahead = (int) DB::query()
            ->fromSub($winningsSubquery, 'w')
            ->where('total_winnings', '>', $mine)
            ->count();

        return [
            'rank' => $ahead + 1,
            'total_winnings' => round($mine, 2),
        ];
    }
}

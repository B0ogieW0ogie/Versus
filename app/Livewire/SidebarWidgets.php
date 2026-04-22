<?php

namespace App\Livewire;

use App\Models\Battle;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class SidebarWidgets extends Component
{
    public ?Battle $battle = null;

    #[On('battle-voted')]
    public function refreshStats(): void
    {
        // no-op: render() reads fresh data.
    }

    public function render(): View
    {
        $trending = Battle::query()
            ->where('status', Battle::STATUS_ACTIVE)
            ->when($this->battle?->id, fn ($q, $id) => $q->where('id', '!=', $id))
            ->orderByDesc('total_pool')
            ->limit(3)
            ->get(['id', 'slug', 'title', 'side_a_label', 'side_b_label', 'total_pool']);

        $winningsSubquery = Vote::query()
            ->join('battles', 'battles.id', '=', 'votes.battle_id')
            ->where('battles.status', Battle::STATUS_SETTLED)
            ->whereNotNull('battles.winning_side')
            ->whereColumn('votes.side', 'battles.winning_side')
            ->selectRaw('votes.user_id, COALESCE(SUM(votes.payout), 0) AS total_winnings')
            ->groupBy('votes.user_id')
            ->toBase();

        $topPlayers = User::query()
            ->select('users.id', 'users.name')
            ->leftJoinSub($winningsSubquery, 'w', fn ($j) => $j->on('w.user_id', '=', 'users.id'))
            ->selectRaw('COALESCE(w.total_winnings, 0) AS total_winnings')
            ->orderByDesc('total_winnings')
            ->orderBy('users.id')
            ->limit(3)
            ->get();

        $jackpot = (float) Transaction::query()
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN type = ? THEN amount WHEN type = ? THEN -amount ELSE 0 END), 0) as pool',
                [Transaction::TYPE_REWARD_POOL_CREDIT, Transaction::TYPE_REWARD_POOL_DEBIT]
            )
            ->value('pool');

        return view('livewire.sidebar-widgets', [
            'trending' => $trending,
            'topPlayers' => $topPlayers,
            'jackpot' => max(0.0, $jackpot),
        ]);
    }
}

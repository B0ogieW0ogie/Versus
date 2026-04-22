<?php

namespace App\Livewire;

use App\Models\Battle;
use App\Models\Vote;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class MyBets extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'active';

    public function selectTab(string $tab): void
    {
        $this->tab = in_array($tab, ['active', 'settled'], true) ? $tab : 'active';
        $this->resetPage();
    }

    #[Layout('layouts.app')]
    public function render(): View
    {
        $query = Vote::query()
            ->where('user_id', Auth::id())
            ->with(['battle:id,title,slug,status,side_a_label,side_b_label,winning_side,total_pool,closes_at,settled_at']);

        if ($this->tab === 'active') {
            $query->whereHas('battle', fn ($b) => $b->where('status', Battle::STATUS_ACTIVE));
        } else {
            $query->whereHas('battle', fn ($b) => $b->whereIn('status', [Battle::STATUS_SETTLED, Battle::STATUS_CLOSED]));
        }

        $votes = $query->latest()->paginate(20);

        return view('livewire.my-bets', [
            'votes' => $votes,
        ]);
    }

    public function statusFor(Vote $vote): string
    {
        $battle = $vote->battle;

        if ($battle->status === Battle::STATUS_ACTIVE) {
            return 'active';
        }

        if ($battle->winning_side === null) {
            return 'refund';
        }

        return $vote->side === $battle->winning_side ? 'won' : 'lost';
    }

    public function netAmountFor(Vote $vote): float
    {
        return match ($this->statusFor($vote)) {
            'won', 'refund' => (float) ($vote->payout ?? 0),
            'lost' => -(float) $vote->amount,
            default => 0.0,
        };
    }
}

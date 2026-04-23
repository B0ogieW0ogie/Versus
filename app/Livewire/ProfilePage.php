<?php

namespace App\Livewire;

use App\Models\Battle;
use App\Models\Comment;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ProfilePage extends Component
{
    use WithPagination;

    private const TABS = ['activity', 'creation', 'comments', 'referrals'];

    #[Url]
    public string $tab = 'activity';

    public function mount(): void
    {
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'activity';
        }
    }

    public function selectTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'activity';
        $this->resetPage();
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

        return $vote->side === $battle->winning_side ? 'win' : 'lose';
    }

    public function netAmountFor(Vote $vote): float
    {
        return match ($this->statusFor($vote)) {
            'win', 'refund' => (float) ($vote->payout ?? 0),
            'lose' => -(float) $vote->amount,
            default => 0.0,
        };
    }

    #[Layout('layouts.app')]
    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        return view('livewire.profile-page', [
            'user' => $user,
            'votes' => $this->loadVotes($user),
            'comments' => $this->loadComments($user),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Vote>
     */
    private function loadVotes(User $user): LengthAwarePaginator
    {
        return Vote::query()
            ->where('user_id', $user->id)
            ->with(['battle:id,title,slug,status,side_a_label,side_b_label,winning_side,total_pool,closes_at,settled_at'])
            ->latest()
            ->paginate(20);
    }

    /**
     * @return LengthAwarePaginator<int, Comment>
     */
    private function loadComments(User $user): LengthAwarePaginator
    {
        return Comment::query()
            ->where('user_id', $user->id)
            ->with(['battle:id,slug,title'])
            ->latest()
            ->paginate(20);
    }
}

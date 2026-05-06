<?php

namespace App\Livewire;

use App\Models\Battle;
use App\Models\Comment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
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

    public function statusFor(Battle $battle): string
    {
        if ($battle->status === Battle::STATUS_ACTIVE) {
            return 'active';
        }

        if ($battle->winning_side === null) {
            return 'refund';
        }

        return $this->selectedSideFor($battle) === $battle->winning_side ? 'win' : 'lose';
    }

    public function selectedSideFor(Battle $battle): string
    {
        $sumA = (float) ($battle->user_side_a_stake ?? 0);
        $sumB = (float) ($battle->user_side_b_stake ?? 0);

        if ($sumA > $sumB) {
            return Battle::SIDE_A;
        }

        if ($sumB > $sumA) {
            return Battle::SIDE_B;
        }

        if ($battle->winning_side === Battle::SIDE_A) {
            return Battle::SIDE_B;
        }

        if ($battle->winning_side === Battle::SIDE_B) {
            return Battle::SIDE_A;
        }

        return Battle::SIDE_A;
    }

    public function selectedStakeFor(Battle $battle): float
    {
        return $this->selectedSideFor($battle) === Battle::SIDE_A
            ? (float) ($battle->user_side_a_stake ?? 0)
            : (float) ($battle->user_side_b_stake ?? 0);
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
            'referrals' => $this->loadReferrals($user),
            'referralUrl' => url('/?ref='.$user->referral_code),
            'referralEarned' => $this->loadReferralEarned($user),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Battle>
     */
    private function loadVotes(User $user): LengthAwarePaginator
    {
        return Battle::query()
            ->select(['id', 'title', 'slug', 'status', 'side_a_label', 'side_b_label', 'winning_side', 'closes_at', 'settled_at'])
            ->whereHas('votes', function (Builder $query) use ($user): void {
                $query->where('user_id', $user->id);
            })
            ->withSum(['votes as user_total_stake' => function (Builder $query) use ($user): void {
                $query->where('user_id', $user->id);
            }], 'amount')
            ->withSum(['votes as user_side_a_stake' => function (Builder $query) use ($user): void {
                $query->where('user_id', $user->id)->where('side', Battle::SIDE_A);
            }], 'amount')
            ->withSum(['votes as user_side_b_stake' => function (Builder $query) use ($user): void {
                $query->where('user_id', $user->id)->where('side', Battle::SIDE_B);
            }], 'amount')
            ->withMax(['votes as user_last_voted_at' => function (Builder $query) use ($user): void {
                $query->where('user_id', $user->id);
            }], 'created_at')
            ->orderByDesc('user_last_voted_at')
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

    /**
     * @return Collection<int, User>
     */
    private function loadReferrals(User $user)
    {
        return User::query()
            ->where('referred_by_id', $user->id)
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'created_at']);
    }

    private function loadReferralEarned(User $user): float
    {
        return (float) Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', Transaction::TYPE_REFERRAL_REWARD)
            ->sum('amount');
    }
}

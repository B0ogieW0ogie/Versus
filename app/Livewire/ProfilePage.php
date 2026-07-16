<?php

namespace App\Livewire;

use App\Actions\Users\ToggleFollowAction;
use App\Models\Battle;
use App\Models\Comment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ProfilePage extends Component
{
    use WithPagination;

    private const TABS = ['activity', 'creation', 'comments', 'referrals'];

    /** Tabs that expose the owner's private data and are never shown to other viewers. */
    private const PRIVATE_TABS = ['activity', 'referrals'];

    #[Url]
    public string $tab = 'activity';

    /**
     * Id of the profile being viewed (defaults to the authenticated user).
     * Locked so a client cannot swap it via a Livewire update to reach another
     * user's private tabs — mount() is the only place it may be set.
     */
    #[Locked]
    public int $userId;

    public function mount(?User $user = null): void
    {
        $this->userId = $user !== null ? $user->id : (int) Auth::id();

        if (! in_array($this->tab, self::TABS, true) || ! $this->tabIsAllowed($this->tab)) {
            $this->tab = $this->defaultTab();
        }
    }

    public function selectTab(string $tab): void
    {
        $this->tab = (in_array($tab, self::TABS, true) && $this->tabIsAllowed($tab)) ? $tab : $this->defaultTab();
        $this->resetPage();
    }

    public function isOwnProfile(): bool
    {
        return $this->userId === (int) Auth::id();
    }

    /** Activity (bets) and referrals are private to the profile owner. */
    private function tabIsAllowed(string $tab): bool
    {
        return ! in_array($tab, self::PRIVATE_TABS, true) || $this->isOwnProfile();
    }

    /** First tab a viewer is allowed to see: the owner lands on activity, others on creation. */
    private function defaultTab(): string
    {
        return $this->isOwnProfile() ? 'activity' : 'creation';
    }

    public function toggleFollow(ToggleFollowAction $action): void
    {
        if ($this->isOwnProfile()) {
            return;
        }

        /** @var User $me */
        $me = Auth::user();
        $action->execute($me, User::findOrFail($this->userId));
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
        $user = User::findOrFail($this->userId);
        $isOwnProfile = $this->isOwnProfile();

        /** @var User $viewer */
        $viewer = Auth::user();

        return view('livewire.profile-page', [
            'user' => $user,
            'isOwnProfile' => $isOwnProfile,
            'isFollowing' => ! $isOwnProfile && $viewer->isFollowing($user),
            'followersCount' => $user->followers()->count(),
            'followingCount' => $user->following()->count(),
            // Activity is owner-only; never query another user's bets.
            'votes' => $isOwnProfile ? $this->loadVotes($user) : new Paginator([], 0, 20),
            'created' => $this->loadCreatedBattles($user),
            'comments' => $this->loadComments($user),
            // Referral details are only ever rendered on the owner's own profile.
            'referrals' => $isOwnProfile ? $this->loadReferrals($user) : new Collection,
            'referralUrl' => $isOwnProfile ? url('/?ref='.$user->referral_code) : '',
            'referralEarned' => $isOwnProfile ? $this->loadReferralEarned($user) : 0.0,
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Battle>
     */
    private function loadVotes(User $user): LengthAwarePaginator
    {
        return Battle::query()
            ->select([
                'id',
                'title',
                'slug',
                'status',
                'side_a_label',
                'side_b_label',
                'side_a_image',
                'side_b_image',
                'winning_side',
                'closes_at',
                'settled_at',
            ])
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
     * @return LengthAwarePaginator<int, Battle>
     */
    private function loadCreatedBattles(User $user): LengthAwarePaginator
    {
        return Battle::query()
            ->select([
                'id',
                'title',
                'slug',
                'status',
                'side_a_label',
                'side_b_label',
                'side_a_image',
                'side_b_image',
                'winning_side',
                'total_pool',
                'closes_at',
                'settled_at',
                'created_at',
            ])
            ->where('created_by_id', $user->id)
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

    /**
     * @return Collection<int, User>
     */
    private function loadReferrals(User $user)
    {
        return User::query()
            ->where('referred_by_id', $user->id)
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'created_at', 'is_admin']);
    }

    private function loadReferralEarned(User $user): float
    {
        return (float) Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', Transaction::TYPE_REFERRAL_REWARD)
            ->sum('amount');
    }
}

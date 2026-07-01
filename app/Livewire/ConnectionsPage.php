<?php

namespace App\Livewire;

use App\Actions\Users\ToggleFollowAction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class ConnectionsPage extends Component
{
    use WithPagination;

    private const TYPES = ['subscribers', 'following'];

    public int $userId;

    public string $type = 'subscribers';

    public function mount(User $user, string $type = 'subscribers'): void
    {
        $this->userId = $user->id;
        $this->type = in_array($type, self::TYPES, true) ? $type : 'subscribers';
    }

    public function toggleFollow(int $personId, ToggleFollowAction $action): void
    {
        if ($personId === (int) Auth::id()) {
            return;
        }

        /** @var User $me */
        $me = Auth::user();
        $action->execute($me, User::findOrFail($personId));
    }

    #[Layout('layouts.app')]
    public function render(): View
    {
        $user = User::findOrFail($this->userId);

        // "subscribers" = people who follow this user; "following" = people they follow.
        $relation = $this->type === 'subscribers' ? $user->followers() : $user->following();

        /** @var LengthAwarePaginator<int, User> $people */
        $people = $relation
            ->select(['users.id', 'users.name', 'users.username', 'users.avatar_path', 'users.is_admin'])
            ->orderBy('name')
            ->paginate(30);

        /** @var User $viewer */
        $viewer = Auth::user();
        $followingIds = $viewer->following()->pluck('users.id')->all();

        return view('livewire.connections-page', [
            'people' => $people,
            'profileUser' => $user,
            'viewerId' => (int) $viewer->id,
            'followingIds' => $followingIds,
        ]);
    }
}

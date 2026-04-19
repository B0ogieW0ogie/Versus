<?php

namespace App\Livewire;

use App\Actions\Battles\CastVoteAction;
use App\Models\Battle;
use App\Models\Comment;
use App\Models\Vote;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

class BattleShow extends Component
{
    public Battle $battle;

    public string $commentBody = '';

    public ?string $commentSide = null;

    public function mount(Battle $battle): void
    {
        $this->battle = $battle;
    }

    public function voteFor(string $side, CastVoteAction $action): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login');

            return;
        }

        if (! in_array($side, [Battle::SIDE_A, Battle::SIDE_B], true)) {
            $this->addError('vote', __('battle.invalid_side'));

            return;
        }

        try {
            $action(Auth::user(), $this->battle, $side, 1.0);
            $this->battle->refresh();
            session()->flash('battle-status', __('battle.vote_cast'));
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->addError('vote', $message);
                }
            }
        }
    }

    public function comment(): void
    {
        $this->validate([
            'commentBody' => ['required', 'string', 'max:1000'],
            'commentSide' => ['nullable', 'in:A,B'],
        ]);

        Comment::create([
            'user_id' => Auth::id(),
            'battle_id' => $this->battle->id,
            'body' => $this->commentBody,
            'side' => $this->commentSide,
        ]);

        $this->commentBody = '';
        $this->commentSide = null;
    }

    #[Layout('layouts.app')]
    public function render(): View
    {
        $stats = Vote::where('battle_id', $this->battle->id)
            ->selectRaw('side, COALESCE(SUM(amount), 0) AS amount_sum, COUNT(*) AS vote_count')
            ->groupBy('side')
            ->get()
            ->keyBy('side');

        $poolA = (float) ($stats->get('A')->amount_sum ?? 0);
        $poolB = (float) ($stats->get('B')->amount_sum ?? 0);

        $userStakeA = 0.0;
        $userStakeB = 0.0;
        $userTotalStaked = 0.0;
        $user = Auth::user();
        if ($user !== null) {
            $userStats = Vote::where('user_id', $user->id)
                ->where('battle_id', $this->battle->id)
                ->selectRaw('side, COALESCE(SUM(amount), 0) AS amount_sum')
                ->groupBy('side')
                ->pluck('amount_sum', 'side');

            $userStakeA = (float) ($userStats['A'] ?? 0);
            $userStakeB = (float) ($userStats['B'] ?? 0);
            $userTotalStaked = (float) Vote::where('user_id', $user->id)->sum('amount');
        }

        return view('livewire.battle-show', [
            'poolA' => $poolA,
            'poolB' => $poolB,
            'votesA' => (int) ($stats->get('A')->vote_count ?? 0),
            'votesB' => (int) ($stats->get('B')->vote_count ?? 0),
            'userStakeA' => $userStakeA,
            'userStakeB' => $userStakeB,
            'userTotalStaked' => $userTotalStaked,
            'voteCap' => (float) config('versus.vote_cap_per_user'),
            'comments' => $this->battle->comments()->with('user')->latest()->get(),
        ]);
    }
}

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

    public function voteFor(string $side, int $amount, CastVoteAction $action): void
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
            $action(Auth::user(), $this->battle, $side, (float) $amount);
            $this->battle->refresh();
            Auth::user()?->refresh();
            $this->dispatch('battle-voted');
            $this->dispatch('balance-updated', balance: (int) Auth::user()->balance);
            session()->flash('battle-status', __('battle.vote_cast'));
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->addError('vote', $message);
                }
            }
        }
    }

    public function supportFor(int $commentId, CastVoteAction $action): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login');

            return;
        }

        $comment = $this->battle->comments()->find($commentId);
        if ($comment === null || ! in_array($comment->side, [Battle::SIDE_A, Battle::SIDE_B], true)) {
            return;
        }

        try {
            $action(Auth::user(), $this->battle, $comment->side, 1.0);
            $this->battle->refresh();
            Auth::user()?->refresh();
            $this->dispatch('battle-voted');
            $this->dispatch('balance-updated', balance: (int) Auth::user()->balance);
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
        $user = Auth::user();
        if ($user !== null) {
            $userStats = Vote::where('user_id', $user->id)
                ->where('battle_id', $this->battle->id)
                ->selectRaw('side, COALESCE(SUM(amount), 0) AS amount_sum')
                ->groupBy('side')
                ->pluck('amount_sum', 'side');

            $userStakeA = (float) ($userStats['A'] ?? 0);
            $userStakeB = (float) ($userStats['B'] ?? 0);
        }

        return view('livewire.battle-show', [
            'poolA' => $poolA,
            'poolB' => $poolB,
            'votesA' => (int) ($stats->get('A')->vote_count ?? 0),
            'votesB' => (int) ($stats->get('B')->vote_count ?? 0),
            'userStakeA' => $userStakeA,
            'userStakeB' => $userStakeB,
            'maxVoteAmount' => (int) config('versus.max_vote_amount'),
            'comments' => Comment::query()
                ->where('battle_id', $this->battle->id)
                ->with('user:id,name')
                ->select('comments.*')
                ->addSelect([
                    'author_side_votes_sum' => Vote::query()
                        ->selectRaw('COALESCE(SUM(amount), 0)')
                        ->whereColumn('votes.user_id', 'comments.user_id')
                        ->whereColumn('votes.battle_id', 'comments.battle_id')
                        ->whereColumn('votes.side', 'comments.side'),
                ])
                ->latest()
                ->limit(50)
                ->get(),
        ]);
    }
}

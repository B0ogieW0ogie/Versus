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
use Livewire\Attributes\On;
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

    #[On('battle-voted')]
    public function refreshBattle(): void
    {
        $this->battle->refresh();
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
        $totalPool = (float) Vote::query()
            ->where('battle_id', $this->battle->id)
            ->sum('amount');

        return view('livewire.battle-show', [
            'totalPool' => $totalPool,
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

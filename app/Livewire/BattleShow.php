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

    public string $voteSide = Battle::SIDE_A;

    public ?float $voteAmount = null;

    public string $commentBody = '';

    public ?string $commentSide = null;

    public function mount(Battle $battle): void
    {
        $this->battle = $battle;
    }

    public function vote(CastVoteAction $action): void
    {
        $this->validate([
            'voteSide' => ['required', 'in:A,B'],
            'voteAmount' => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            $action(Auth::user(), $this->battle, $this->voteSide, (float) $this->voteAmount);
            $this->voteAmount = null;
            $this->battle->refresh();
            session()->flash('battle-status', 'Ставка принята.');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->addError('voteAmount', $message);
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

        $userVote = null;
        $user = Auth::user();
        if ($user !== null) {
            $userVote = Vote::where('user_id', $user->id)
                ->where('battle_id', $this->battle->id)
                ->first();
        }

        return view('livewire.battle-show', [
            'poolA' => $poolA,
            'poolB' => $poolB,
            'votesA' => (int) ($stats->get('A')->vote_count ?? 0),
            'votesB' => (int) ($stats->get('B')->vote_count ?? 0),
            'userVote' => $userVote,
            'comments' => $this->battle->comments()->with('user')->latest()->get(),
        ]);
    }
}

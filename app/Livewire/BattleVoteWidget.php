<?php

namespace App\Livewire;

use App\Actions\Battles\CastVoteAction;
use App\Models\Battle;
use App\Models\Vote;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class BattleVoteWidget extends Component
{
    public Battle $battle;

    public function mount(Battle $battle): void
    {
        $this->battle = $battle;
    }

    #[On('battle-voted')]
    public function refreshStats(): void
    {
        $this->battle->refresh();
    }

    public function castVote(string $side, int $amount, CastVoteAction $action): void
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
            $this->dispatch(
                'versus-stake-toast',
                title: str_replace('__N__', (string) (int) $amount, __('battle.stake_modal_title')),
                body: __('battle.stake_modal_body'),
                battleId: $this->battle->id,
                poolTotal: (float) $this->battle->total_pool,
            );
            $this->dispatch('balance-updated', balance: (int) Auth::user()->balance);
            $this->dispatch('battle-voted');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->addError('vote', $message);
                }
            }
        }
    }

    public function render(): View
    {
        $totalPool = (float) Vote::query()
            ->where('battle_id', $this->battle->id)
            ->sum('amount');

        $user = Auth::user();
        $userStakeA = 0.0;
        $userStakeB = 0.0;
        if ($user !== null) {
            $userStats = Vote::query()
                ->where('user_id', $user->id)
                ->where('battle_id', $this->battle->id)
                ->selectRaw('side, COALESCE(SUM(amount), 0) AS amount_sum')
                ->groupBy('side')
                ->pluck('amount_sum', 'side');

            $userStakeA = (float) ($userStats['A'] ?? 0);
            $userStakeB = (float) ($userStats['B'] ?? 0);
        }

        $userBalance = $user !== null ? (float) $user->balance : 0.0;
        $maxVoteAmount = (int) config('versus.max_vote_amount');
        $maxAllowed = (int) min((int) $userBalance, $maxVoteAmount);

        $defaultSide = $userStakeB > $userStakeA ? Battle::SIDE_B : Battle::SIDE_A;

        return view('livewire.battle-vote-widget', [
            'totalPool' => $totalPool,
            'userBalance' => $userBalance,
            'maxAllowed' => $maxAllowed,
            'maxVoteAmount' => $maxVoteAmount,
            'defaultSide' => $defaultSide,
            'closesAtIso' => optional($this->battle->closes_at)->toIso8601String(),
            'canVote' => Auth::check()
                && $this->battle->isOpenForVoting()
                && $userBalance >= 1,
        ]);
    }
}

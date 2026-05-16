<?php

namespace App\Livewire;

use App\Models\Battle;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

class BattleShow extends Component
{
    public Battle $battle;

    public function mount(Battle $battle): void
    {
        $this->battle = $battle;
    }

    #[On('battle-voted')]
    public function refreshBattle(): void
    {
        $this->battle->refresh();
    }

    #[Layout('layouts.app')]
    public function render(): View
    {
        return view('livewire.battle-show');
    }
}

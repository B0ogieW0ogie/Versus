<?php

namespace App\Livewire;

use App\Models\Battle;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

class BattleIndex extends Component
{
    #[Layout('layouts.app')]
    public function render(): View
    {
        /** @var Collection<int, Battle> $active */
        $active = Battle::query()
            ->where('status', Battle::STATUS_ACTIVE)
            ->orderByDesc('closes_at')
            ->get();

        /** @var Collection<int, Battle> $settled */
        $settled = Battle::query()
            ->whereIn('status', [Battle::STATUS_SETTLED, Battle::STATUS_CLOSED])
            ->orderByDesc('settled_at')
            ->limit(10)
            ->get();

        return view('livewire.battle-index', [
            'active' => $active,
            'settled' => $settled,
        ]);
    }
}

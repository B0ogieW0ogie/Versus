<?php

namespace App\Livewire;

use App\Models\Battle;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

class BattleIndex extends Component
{
    #[Layout('layouts.app')]
    public function render(): View
    {
        $sponsored = Battle::sponsoredActive();
        $sponsoredIds = $sponsored->pluck('id')->all();

        $hot = Battle::query()
            ->whereIn('status', [Battle::STATUS_ACTIVE, Battle::STATUS_LAST_SHOT])
            ->with('category')
            ->when($sponsoredIds, fn ($q) => $q->whereNotIn('id', $sponsoredIds))
            ->orderByRaw('case when status = ? then 0 else 1 end', [Battle::STATUS_LAST_SHOT])
            ->orderByDesc('total_pool')
            ->limit(10)
            ->get();

        $categoryRails = Category::query()
            ->orderBy('sort_order')
            ->with(['battles' => fn ($q) => $q
                ->active()
                ->when($sponsoredIds, fn ($qq) => $qq->whereNotIn('id', $sponsoredIds))
                ->orderByDesc('total_pool')
                ->limit(10),
            ])
            ->get()
            ->filter(fn (Category $c) => $c->battles->isNotEmpty())
            ->values();

        return view('livewire.battle-index', [
            'sponsored' => $sponsored,
            'hot' => $hot,
            'categoryRails' => $categoryRails,
        ]);
    }
}

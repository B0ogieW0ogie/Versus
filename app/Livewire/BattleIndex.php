<?php

namespace App\Livewire;

use App\Models\Battle;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class BattleIndex extends Component
{
    use WithPagination;

    #[Url(as: 'category')]
    public ?string $category = null;

    #[Url(as: 'finished')]
    public bool $finished = false;

    public function selectCategory(?string $slug): void
    {
        $this->category = $slug;
        $this->finished = false;
        $this->resetPage();
    }

    public function toggleFinished(): void
    {
        $this->finished = true;
        $this->category = null;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->category = null;
        $this->finished = false;
        $this->resetPage();
    }

    #[Layout('layouts.app')]
    public function render(): View
    {
        $featured = $this->finished ? null : Battle::resolveFeatured();

        $hot = $this->finished
            ? collect()
            : Battle::query()->active()
                ->with('category')
                ->when($featured, fn ($q, $f) => $q->whereKeyNot($f->id))
                ->orderByDesc('total_pool')
                ->limit(3)
                ->get();

        $categories = Category::query()->orderBy('sort_order')->get();

        $exclude = array_filter([$featured?->id, ...$hot->pluck('id')->all()]);

        $allQuery = $this->finished
            ? Battle::query()
                ->with('category')
                ->whereIn('status', [Battle::STATUS_SETTLED, Battle::STATUS_CLOSED])
                ->orderByDesc('settled_at')
            : Battle::query()->active()
                ->with('category')
                ->when(! empty($exclude), fn ($q) => $q->whereNotIn('id', $exclude))
                ->when($this->category, fn ($q, $slug) => $q->whereHas('category', fn ($qq) => $qq->where('slug', $slug))
                )
                ->orderByDesc('closes_at');

        $all = $allQuery->paginate(10);

        return view('livewire.battle-index', [
            'featured' => $featured,
            'hot' => $hot,
            'categories' => $categories,
            'all' => $all,
        ]);
    }
}

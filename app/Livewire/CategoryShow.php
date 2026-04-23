<?php

namespace App\Livewire;

use App\Models\Battle;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class CategoryShow extends Component
{
    use WithPagination;

    public Category $category;

    public function mount(Category $category): void
    {
        $this->category = $category;
    }

    #[Layout('layouts.app')]
    public function render(): View
    {
        $battles = Battle::query()
            ->active()
            ->withSideWeights()
            ->where('category_id', $this->category->id)
            ->with('category')
            ->orderByDesc('closes_at')
            ->paginate(20);

        return view('livewire.category-show', [
            'battles' => $battles,
        ]);
    }
}

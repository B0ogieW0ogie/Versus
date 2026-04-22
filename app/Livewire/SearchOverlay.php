<?php

namespace App\Livewire;

use App\Models\Battle;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

class SearchOverlay extends Component
{
    public string $query = '';

    public function render(): View
    {
        $trimmed = trim($this->query);
        $results = mb_strlen($trimmed) < 2
            ? new Collection
            : $this->search($trimmed);

        return view('livewire.search-overlay', [
            'results' => $results,
            'queryLength' => mb_strlen($trimmed),
        ]);
    }

    /**
     * @return Collection<int, Battle>
     */
    private function search(string $trimmed): Collection
    {
        $needle = '%'.mb_strtolower($trimmed).'%';

        return Battle::query()
            ->with('category')
            ->where(function (Builder $w) use ($needle) {
                $w->whereRaw('LOWER(title) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(side_a_label) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(side_b_label) LIKE ?', [$needle]);
            })
            ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('total_pool')
            ->limit(15)
            ->get();
    }
}

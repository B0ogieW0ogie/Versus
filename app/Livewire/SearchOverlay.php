<?php

namespace App\Livewire;

use App\Models\Battle;
use App\Models\Challenge;
use App\Models\User;
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
        $ready = mb_strlen($trimmed) >= 2;
        $battles = (bool) config('versus.battles_enabled');

        return view('livewire.search-overlay', [
            'battlesMode' => $battles,
            'results' => $ready && $battles ? $this->search($trimmed) : new Collection,
            'people' => $ready && ! $battles ? $this->people($trimmed) : new Collection,
            'challenges' => $ready && ! $battles ? $this->challenges($trimmed) : new Collection,
            'queryLength' => mb_strlen($trimmed),
        ]);
    }

    /** @return Collection<int, User> */
    private function people(string $trimmed): Collection
    {
        $query = mb_strtolower(ltrim($trimmed, '@'));
        $needle = '%'.$query.'%';

        return User::query()
            ->where(fn (Builder $w) => $w->whereRaw('LOWER(username) LIKE ?', [$needle])->orWhereRaw('LOWER(name) LIKE ?', [$needle]))
            ->orderByRaw('CASE WHEN LOWER(username) = ? THEN 0 ELSE 1 END', [$query])
            ->orderBy('username')
            ->limit(8)
            ->get();
    }

    /** @return Collection<int, Challenge> */
    private function challenges(string $trimmed): Collection
    {
        $needle = '%'.mb_strtolower($trimmed).'%';

        return Challenge::query()
            ->with('user')
            ->whereIn('status', [Challenge::STATUS_ACTIVE, Challenge::STATUS_CLOSED])
            ->where(fn (Builder $w) => $w->whereRaw('LOWER(title) LIKE ?', [$needle])->orWhereRaw('LOWER(rules) LIKE ?', [$needle]))
            ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('feed_score')
            ->limit(10)
            ->get();
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

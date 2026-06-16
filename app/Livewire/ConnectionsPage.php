<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

class ConnectionsPage extends Component
{
    private const TYPES = ['subscribers', 'following'];

    public string $type = 'subscribers';

    public function mount(string $type = 'subscribers'): void
    {
        $this->type = in_array($type, self::TYPES, true) ? $type : 'subscribers';
    }

    #[Layout('layouts.app')]
    public function render(): View
    {
        return view('livewire.connections-page', [
            // Subscribers / following are not wired to a data model yet — render an
            // empty list for now. Replace with a real query when the feature lands.
            'connections' => [],
        ]);
    }
}

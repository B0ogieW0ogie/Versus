<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class VerifiedWelcomeModal extends Component
{
    public bool $open = false;

    public int $slide = 0;

    public function mount(): void
    {
        $this->open = (bool) session()->get('show_verified_welcome');
    }

    public function next(): void
    {
        $this->slide = min(2, $this->slide + 1);
    }

    public function prev(): void
    {
        $this->slide = max(0, $this->slide - 1);
    }

    public function start(): void
    {
        session()->forget('show_verified_welcome');
        $this->open = false;
        $this->slide = 0;
    }

    public function render(): View
    {
        return view('livewire.verified-welcome-modal');
    }
}

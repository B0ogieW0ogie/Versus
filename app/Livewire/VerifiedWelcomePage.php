<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

class VerifiedWelcomePage extends Component
{
    public int $slide = 0;

    public function mount(): void
    {
        if (! session()->get('show_verified_welcome')) {
            $this->redirectRoute('home');
        }
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
        $this->redirect(route('home'), navigate: true);
    }

    #[Layout('layouts.app')]
    public function render(): View
    {
        return view('livewire.verified-welcome-page');
    }
}

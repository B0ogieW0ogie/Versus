<?php

namespace App\Livewire;

use App\Http\Middleware\SetLocale;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class LocaleSwitcher extends Component
{
    public function switch(string $locale): void
    {
        if (in_array($locale, SetLocale::SUPPORTED, true)) {
            session()->put('locale', $locale);
        }

        $this->redirect(request()->header('Referer') ?? url('/'), navigate: false);
    }

    public function render(): View
    {
        return view('livewire.locale-switcher', [
            'current' => app()->getLocale(),
            'supported' => SetLocale::SUPPORTED,
        ]);
    }
}

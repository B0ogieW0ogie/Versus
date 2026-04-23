<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

class ProfilePage extends Component
{
    private const TABS = ['activity', 'creation', 'comments', 'referrals'];

    #[Url]
    public string $tab = 'activity';

    public function mount(): void
    {
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'activity';
        }
    }

    public function selectTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'activity';
    }

    #[Layout('layouts.app')]
    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        return view('livewire.profile-page', [
            'user' => $user,
        ]);
    }
}

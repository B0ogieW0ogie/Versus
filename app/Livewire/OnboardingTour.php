<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class OnboardingTour extends Component
{
    public function mount(): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        if ($user->is_first_visit && $user->onboarding_step === 2 && request()->routeIs('profile.edit')) {
            if (request()->query('tab', 'activity') !== 'referrals') {
                $this->redirect(route('profile.edit', ['tab' => 'referrals']), navigate: true);
            }
        }
    }

    public function advance(): void
    {
        $user = Auth::user();
        if ($user === null || ! $user->is_first_visit || $user->onboarding_step === null) {
            return;
        }

        $step = (int) $user->onboarding_step;

        if ($step >= 6) {
            $this->complete();

            return;
        }

        $next = $step + 1;
        $user->forceFill(['onboarding_step' => $next])->save();

        if ($next === 2) {
            $this->redirect(route('profile.edit', ['tab' => 'referrals']), navigate: true);

            return;
        }

    }

    public function copyReferralAndAdvance(): void
    {
        $user = Auth::user();
        if ($user === null || ! $user->is_first_visit || (int) $user->onboarding_step !== 2) {
            return;
        }

        $url = url('/?ref='.$user->referral_code);
        $user->forceFill(['onboarding_step' => 3])->save();
        $this->js('navigator.clipboard.writeText('.json_encode($url).')');
    }

    private function complete(): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $user->forceFill([
            'is_first_visit' => false,
            'onboarding_step' => null,
        ])->save();

        session()->flash('toast_onboarding', __('onboarding.complete_toast'));
        $this->redirect(route('profile.edit'), navigate: false);
    }

    public function render(): View
    {
        $user = Auth::user();

        if ($user === null || ! $user->is_first_visit || $user->onboarding_step === null) {
            return view('livewire.onboarding-tour', [
                'active' => false,
                'step' => 0,
                'bonusAmount' => 0,
            ]);
        }

        return view('livewire.onboarding-tour', [
            'active' => true,
            'step' => (int) $user->onboarding_step,
            'bonusAmount' => (int) round((float) config('versus.signup_bonus'), 0),
        ]);
    }
}

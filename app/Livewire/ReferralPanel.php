<?php

namespace App\Livewire;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

class ReferralPanel extends Component
{
    #[Layout('layouts.app')]
    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        $referrals = User::where('referred_by_id', $user->id)
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'created_at']);

        $earned = (float) Transaction::where('user_id', $user->id)
            ->where('type', Transaction::TYPE_REFERRAL_REWARD)
            ->sum('amount');

        return view('livewire.referral-panel', [
            'user' => $user,
            'referralUrl' => url('/?ref='.$user->referral_code),
            'referrals' => $referrals,
            'earned' => $earned,
        ]);
    }
}

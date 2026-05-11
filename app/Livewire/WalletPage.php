<?php

namespace App\Livewire;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class WalletPage extends Component
{
    use WithPagination;

    #[Layout('layouts.app')]
    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        return view('livewire.wallet-page', [
            'transactions' => $this->loadTransactions($user),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Transaction>
     */
    private function loadTransactions(User $user): LengthAwarePaginator
    {
        return Transaction::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->paginate(25);
    }
}

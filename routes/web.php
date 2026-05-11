<?php

use App\Http\Controllers\ProfileController;
use App\Livewire\BattleCreate;
use App\Livewire\BattleIndex;
use App\Livewire\BattleShow;
use App\Livewire\CategoryShow;
use App\Livewire\Leaderboard;
use App\Livewire\ProfilePage;
use App\Livewire\WalletPage;
use Illuminate\Support\Facades\Route;

Route::get('/', BattleIndex::class)->name('home');
Route::get('/battles', BattleIndex::class)->name('battles.index');
Route::middleware(['auth', 'verified'])->get('/battles/create', BattleCreate::class)->name('battles.create');
Route::get('/battles/{battle:slug}', BattleShow::class)->name('battles.show');
Route::get('/categories/{category:slug}', CategoryShow::class)->name('categories.show');
Route::get('/leaderboard', Leaderboard::class)->name('leaderboard');

Route::get('/dashboard', function () {
    return redirect()->route('home');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/wallet', WalletPage::class)->name('wallet');
    Route::get('/profile', ProfilePage::class)->name('profile.edit');
    Route::get('/profile/settings', [ProfileController::class, 'edit'])->name('profile.settings');
    Route::patch('/profile/settings', [ProfileController::class, 'update'])->name('profile.settings.update');
    Route::delete('/profile/settings', [ProfileController::class, 'destroy'])->name('profile.settings.destroy');

    Route::get('/referrals', fn () => redirect()->route('profile.edit', ['tab' => 'referrals'], 301))->name('referrals');
    Route::get('/my-bets', fn () => redirect()->route('profile.edit', ['tab' => 'activity'], 301))->name('my-bets');
});

require __DIR__.'/auth.php';

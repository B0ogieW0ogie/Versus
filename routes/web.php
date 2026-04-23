<?php

use App\Http\Controllers\ProfileController;
use App\Livewire\BattleIndex;
use App\Livewire\BattleShow;
use App\Livewire\Leaderboard;
use App\Livewire\MyBets;
use App\Livewire\ReferralPanel;
use Illuminate\Support\Facades\Route;

Route::get('/', BattleIndex::class)->name('home');
Route::get('/battles', BattleIndex::class)->name('battles.index');
Route::get('/battles/{battle:slug}', BattleShow::class)->name('battles.show');
Route::get('/categories/{category:slug}', \App\Livewire\CategoryShow::class)->name('categories.show');
Route::get('/leaderboard', Leaderboard::class)->name('leaderboard');

Route::get('/dashboard', function () {
    return redirect()->route('home');
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/referrals', ReferralPanel::class)->name('referrals');
    Route::get('/my-bets', MyBets::class)->name('my-bets');
});

require __DIR__.'/auth.php';

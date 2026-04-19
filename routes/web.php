<?php

use App\Http\Controllers\ProfileController;
use App\Livewire\BattleIndex;
use App\Livewire\BattleShow;
use App\Livewire\ReferralPanel;
use Illuminate\Support\Facades\Route;

Route::get('/', BattleIndex::class)->name('home');
Route::get('/battles', BattleIndex::class)->name('battles.index');
Route::get('/battles/{battle:slug}', BattleShow::class)->name('battles.show');

Route::get('/dashboard', function () {
    return redirect()->route('home');
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/referrals', ReferralPanel::class)->name('referrals');
});

require __DIR__.'/auth.php';

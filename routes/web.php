<?php

use App\Http\Controllers\BattlePoolTotalController;
use App\Http\Controllers\BattlePoolTotalsController;
use App\Http\Controllers\ChallengeUploadController;
use App\Http\Controllers\ProfileController;
use App\Livewire\BattleCreate;
use App\Livewire\BattleIndex;
use App\Livewire\BattleShow;
use App\Livewire\CategoryShow;
use App\Livewire\ChallengeFeed;
use App\Livewire\ConnectionsPage;
use App\Livewire\FeedPage;
use App\Livewire\Leaderboard;
use App\Livewire\ProfilePage;
use App\Livewire\WalletPage;
use Illuminate\Support\Facades\Route;

Route::get('/', BattleIndex::class)->name('home');
Route::get('/battles', BattleIndex::class)->name('battles.index');
Route::middleware(['auth', 'verified'])->get('/battles/create', BattleCreate::class)->name('battles.create');
Route::get('/battles/{battle:slug}/pool-total', BattlePoolTotalController::class)->name('battles.pool-total');
Route::get('/battles/pool-totals', BattlePoolTotalsController::class)->name('battles.pool-totals');
Route::get('/battles/{battle:slug}', BattleShow::class)->name('battles.show');
Route::get('/categories/{category:slug}', CategoryShow::class)->name('categories.show');
Route::get('/leaderboard', Leaderboard::class)->name('leaderboard');

Route::get('/challenges', ChallengeFeed::class)->name('challenges.index');
// Replaced by the real form in Task 15.
Route::middleware(['auth', 'verified'])->get('/challenges/create', fn () => abort(404))->name('challenges.create');
Route::middleware(['auth', 'verified'])->get('/c/{challenge:slug}/respond', fn () => abort(404))->name('challenges.respond');
Route::get('/c/{challenge:slug}', ChallengeFeed::class)->name('challenges.show');

Route::get('/dashboard', function () {
    return redirect()->route('home');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/feed', FeedPage::class)->name('feed');
    Route::get('/wallet', WalletPage::class)->name('wallet');
    Route::get('/profile', ProfilePage::class)->name('profile.edit');
    Route::get('/profile/settings', [ProfileController::class, 'edit'])->name('profile.settings');
    Route::patch('/profile/settings', [ProfileController::class, 'update'])->name('profile.settings.update');
    Route::delete('/profile/settings', [ProfileController::class, 'destroy'])->name('profile.settings.destroy');

    // Wildcard {user} routes come after the literal /profile/settings paths above.
    Route::get('/profile/{user}', ProfilePage::class)->name('profile.show');
    Route::get('/profile/{user}/subscribers', ConnectionsPage::class)->defaults('type', 'subscribers')->name('profile.subscribers');
    Route::get('/profile/{user}/following', ConnectionsPage::class)->defaults('type', 'following')->name('profile.following');

    Route::get('/referrals', fn () => redirect()->route('profile.edit', ['tab' => 'referrals'], 301))->name('referrals');
    Route::get('/my-bets', fn () => redirect()->route('profile.edit', ['tab' => 'activity'], 301))->name('my-bets');

    // Replaced by the real page in Task 16.
    Route::get('/my-challenges', fn () => abort(404))->name('challenges.mine');

    Route::post('/challenge-uploads', [ChallengeUploadController::class, 'start'])->name('challenge-uploads.start');
    Route::post('/challenge-uploads/{upload}/chunks', [ChallengeUploadController::class, 'chunk'])->name('challenge-uploads.chunk');
    Route::get('/challenge-uploads/{upload}', [ChallengeUploadController::class, 'status'])->name('challenge-uploads.status');
    Route::post('/challenge-uploads/{upload}/complete', [ChallengeUploadController::class, 'complete'])->name('challenge-uploads.complete');
});

require __DIR__.'/auth.php';

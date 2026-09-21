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
use App\Livewire\ChallengeForm;
use App\Livewire\ConnectionsPage;
use App\Livewire\FeedPage;
use App\Livewire\Leaderboard;
use App\Livewire\MyChallenges;
use App\Livewire\NewsFeed;
use App\Livewire\NotificationsPage;
use App\Livewire\ProfilePage;
use App\Livewire\WalletPage;
use Illuminate\Support\Facades\Route;

// Home is the News Feed; the vertical video feed is the Challenges section.
Route::get('/', NewsFeed::class)->name('home');
Route::get('/battles', BattleIndex::class)->middleware('battles')->name('battles.index');
Route::middleware(['auth', 'verified', 'battles'])->get('/battles/create', BattleCreate::class)->name('battles.create');
Route::get('/battles/{battle:slug}/pool-total', BattlePoolTotalController::class)->name('battles.pool-total');
Route::get('/battles/pool-totals', BattlePoolTotalsController::class)->name('battles.pool-totals');
Route::get('/battles/{battle:slug}', BattleShow::class)->middleware('battles')->name('battles.show');
Route::get('/categories/{category:slug}', CategoryShow::class)->middleware('battles')->name('categories.show');
Route::get('/leaderboard', Leaderboard::class)->middleware('battles')->name('leaderboard');

Route::get('/challenges', ChallengeFeed::class)->name('challenges.index');
Route::middleware(['auth', 'verified'])->get('/challenges/create', ChallengeForm::class)->name('challenges.create');
Route::middleware(['auth', 'verified'])->get('/c/{challenge:slug}/respond', ChallengeForm::class)->name('challenges.respond');
Route::get('/c/{challenge:slug}', ChallengeFeed::class)->name('challenges.show');

Route::get('/dashboard', function () {
    return redirect()->route('home');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/feed', FeedPage::class)->middleware('battles')->name('feed');
    Route::get('/wallet', WalletPage::class)->middleware('battles')->name('wallet');
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

    Route::get('/my-challenges', MyChallenges::class)->name('challenges.mine');
    Route::get('/notifications', NotificationsPage::class)->name('notifications');

    Route::post('/challenge-uploads', [ChallengeUploadController::class, 'start'])
        ->middleware('throttle:challenge-upload-start')->name('challenge-uploads.start');
    Route::middleware('throttle:challenge-upload-chunks')->group(function () {
        Route::post('/challenge-uploads/{upload}/chunks', [ChallengeUploadController::class, 'chunk'])->name('challenge-uploads.chunk');
        Route::get('/challenge-uploads/{upload}', [ChallengeUploadController::class, 'status'])->name('challenge-uploads.status');
        Route::post('/challenge-uploads/{upload}/complete', [ChallengeUploadController::class, 'complete'])->name('challenge-uploads.complete');
    });
});

require __DIR__.'/auth.php';

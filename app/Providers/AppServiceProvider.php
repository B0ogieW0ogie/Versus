<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Video\FfmpegVideoTranscoder;
use App\Services\Video\VideoTranscoder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(VideoTranscoder::class, FfmpegVideoTranscoder::class);
    }

    public function boot(): void
    {
        Gate::define('admin', fn (User $user) => $user->is_admin === true);

        // Upload routes sit behind auth, so the limiters are keyed by the user id.
        RateLimiter::for('challenge-upload-start', fn (Request $request) => Limit::perMinute(10)->by((string) $request->user()->id));
        RateLimiter::for('challenge-upload-chunks', fn (Request $request) => Limit::perMinute(120)->by((string) $request->user()->id));
    }
}

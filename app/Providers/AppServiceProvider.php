<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Video\FfmpegVideoTranscoder;
use App\Services\Video\VideoTranscoder;
use Illuminate\Support\Facades\Gate;
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
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBattlesEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('versus.battles_enabled')) {
            return redirect()->route('challenges.index');
        }

        return $next($request);
    }
}

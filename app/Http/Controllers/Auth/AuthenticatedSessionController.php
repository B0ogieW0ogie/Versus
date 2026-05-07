<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): View
    {
        $email = Str::lower(trim((string) old('email')));
        $throttleKey = Str::transliterate($email.'|'.$request->ip());
        $isRateLimited = $email !== ''
            && RateLimiter::tooManyAttempts($throttleKey, LoginRequest::MAX_ATTEMPTS);
        $secondsUntilUnlock = $isRateLimited
            ? RateLimiter::availableIn($throttleKey)
            : 0;

        return view('auth.login', [
            'isRateLimited' => $isRateLimited,
            'secondsUntilUnlock' => $secondsUntilUnlock,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}

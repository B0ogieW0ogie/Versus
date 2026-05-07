<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Users\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Middleware\CaptureReferralCode;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        $cookieReferralCode = $request->cookie(CaptureReferralCode::COOKIE);

        return view('auth.register', [
            'referralCode' => is_string($cookieReferralCode) ? $cookieReferralCode : null,
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request, RegisterUserAction $register): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'referral_code' => ['nullable', 'string', 'regex:/^[A-Z0-9]{4,16}$/'],
        ], [
            'email.required' => 'Заполните все поля',
            'password.required' => 'Заполните все поля',
        ]);

        $email = Str::lower((string) $validated['email']);
        $name = Str::before($email, '@');

        $referralCode = $validated['referral_code'] ?? $request->cookie(CaptureReferralCode::COOKIE);
        $normalizedReferralCode = is_string($referralCode) ? Str::upper($referralCode) : null;

        $user = $register([
            'name' => $name !== '' ? $name : 'user',
            'email' => $email,
            'password' => $validated['password'],
        ], $normalizedReferralCode);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false))
            ->withoutCookie(CaptureReferralCode::COOKIE);
    }
}

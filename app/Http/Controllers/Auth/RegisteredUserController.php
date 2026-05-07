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
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request, RegisterUserAction $register): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'name.required' => 'Заполните все поля',
            'email.required' => 'Заполните все поля',
            'password.required' => 'Заполните все поля',
        ]);

        $referralCode = $request->cookie(CaptureReferralCode::COOKIE);

        $user = $register($validated, is_string($referralCode) ? $referralCode : null);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false))
            ->withoutCookie(CaptureReferralCode::COOKIE);
    }
}

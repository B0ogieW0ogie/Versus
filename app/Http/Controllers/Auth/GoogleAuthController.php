<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Users\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Middleware\CaptureReferralCode;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        /** @var RedirectResponse $response */
        $response = Socialite::driver('google')->redirect();

        return $response;
    }

    public function callback(RegisterUserAction $register): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Не удалось выполнить вход через Google. Попробуйте еще раз.']);
        }

        $googleId = (string) $googleUser->getId();
        $email = Str::lower(trim((string) $googleUser->getEmail()));

        if ($googleId === '' || $email === '') {
            return redirect()->route('login')
                ->withErrors(['email' => 'Google не вернул обязательные данные аккаунта.']);
        }

        $user = User::where('google_id', $googleId)->first();
        $isNewUser = false;

        if ($user === null) {
            $user = User::where('email', $email)->first();

            if ($user !== null) {
                $user->forceFill([
                    'google_id' => $googleId,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
            } else {
                $name = trim((string) $googleUser->getName());
                if ($name === '') {
                    $name = Str::before($email, '@');
                }

                $referralCode = request()->cookie(CaptureReferralCode::COOKIE);

                $user = $register([
                    'name' => $name,
                    'email' => $email,
                    'password' => Str::password(32),
                ], is_string($referralCode) ? $referralCode : null);

                $user->forceFill([
                    'google_id' => $googleId,
                    'email_verified_at' => now(),
                ])->save();

                event(new Registered($user));
                $isNewUser = true;
            }
        }

        if ($user->email_verified_at === null) {
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();
        }

        Auth::login($user, remember: true);

        $response = redirect(route('dashboard', absolute: false));

        if ($isNewUser) {
            return $response->withoutCookie(CaptureReferralCode::COOKIE);
        }

        return $response;
    }
}

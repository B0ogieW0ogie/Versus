<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Users\CreditSignupBonusAction;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request, CreditSignupBonusAction $creditSignupBonus): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            $creditSignupBonus($request->user());

            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            $creditSignupBonus($request->user());
            event(new Verified($request->user()));
        }

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}

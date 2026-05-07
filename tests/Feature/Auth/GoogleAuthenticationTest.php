<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\CaptureReferralCode;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_redirect_route_returns_provider_redirect(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://accounts.google.com/o/oauth2/v2/auth'));

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $response = $this->get(route('google.redirect'));

        $response->assertRedirect('https://accounts.google.com/o/oauth2/v2/auth');
    }

    public function test_google_callback_creates_new_user_with_signup_bonus_and_referral(): void
    {
        $referrer = User::factory()->create();

        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-123');
        $socialiteUser->shouldReceive('getEmail')->andReturn('new-google-user@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Google User');

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $response = $this->withCookie(CaptureReferralCode::COOKIE, $referrer->referral_code)
            ->get(route('google.callback'));

        $user = User::where('email', 'new-google-user@example.com')->firstOrFail();
        $bonus = (float) config('versus.signup_bonus');

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame($referrer->id, $user->referred_by_id);
        $this->assertSame($bonus, (float) $user->balance);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => Transaction::TYPE_SIGNUP_BONUS,
            'amount' => number_format($bonus, 2, '.', ''),
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertCookieExpired(CaptureReferralCode::COOKIE);
    }

    public function test_google_callback_links_existing_email_account(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'google_id' => null,
            'email_verified_at' => null,
        ]);

        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-existing-777');
        $socialiteUser->shouldReceive('getEmail')->andReturn('existing@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Existing User');

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $response = $this->get(route('google.callback'));

        $existingUser->refresh();

        $this->assertAuthenticatedAs($existingUser);
        $this->assertSame('google-existing-777', $existingUser->google_id);
        $this->assertNotNull($existingUser->email_verified_at);
        $this->assertDatabaseMissing('transactions', [
            'user_id' => $existingUser->id,
            'type' => Transaction::TYPE_SIGNUP_BONUS,
        ]);
        $response->assertRedirect(route('dashboard', absolute: false));
    }
}

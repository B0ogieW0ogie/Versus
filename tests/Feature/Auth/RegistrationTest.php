<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\CaptureReferralCode;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_new_user_receives_signup_bonus_and_referral_code(): void
    {
        $this->post('/register', [
            'name' => 'Bonus User',
            'email' => 'bonus@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'bonus@example.com')->firstOrFail();
        $bonus = (float) config('versus.signup_bonus');

        $this->assertSame($bonus, (float) $user->balance);
        $this->assertNotEmpty($user->referral_code);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $user->referral_code);
        $this->assertNull($user->referred_by_id);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => Transaction::TYPE_SIGNUP_BONUS,
            'amount' => number_format($bonus, 2, '.', ''),
            'balance_after' => number_format($bonus, 2, '.', ''),
        ]);
    }

    public function test_referral_cookie_attributes_new_user_to_referrer(): void
    {
        $referrer = User::factory()->create();

        $response = $this->withCookie(CaptureReferralCode::COOKIE, $referrer->referral_code)
            ->post('/register', [
                'name' => 'Referee User',
                'email' => 'referee@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

        $referee = User::where('email', 'referee@example.com')->firstOrFail();

        $this->assertSame($referrer->id, $referee->referred_by_id);
        $response->assertCookieExpired(CaptureReferralCode::COOKIE);
    }

    public function test_unknown_referral_code_does_not_block_registration(): void
    {
        $this->withCookie(CaptureReferralCode::COOKIE, 'NOPE1234')
            ->post('/register', [
                'name' => 'Orphan User',
                'email' => 'orphan@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

        $user = User::where('email', 'orphan@example.com')->firstOrFail();

        $this->assertNull($user->referred_by_id);
    }

    public function test_referral_query_parameter_sets_cookie(): void
    {
        $response = $this->get('/?ref=ABCDEF12');

        $response->assertCookie(CaptureReferralCode::COOKIE, 'ABCDEF12');
    }
}

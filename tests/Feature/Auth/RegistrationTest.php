<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\CaptureReferralCode;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_PASSWORD = 'StrongPass1!';

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'email' => 'test@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        $user = User::where('email', 'test@example.com')->firstOrFail();

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_new_user_does_not_receive_signup_bonus_before_email_verification(): void
    {
        $this->post('/register', [
            'email' => 'bonus@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        $user = User::where('email', 'bonus@example.com')->firstOrFail();

        $this->assertSame(0.0, (float) $user->balance);
        $this->assertNotEmpty($user->referral_code);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $user->referral_code);
        $this->assertNull($user->referred_by_id);

        $this->assertDatabaseMissing('transactions', [
            'user_id' => $user->id,
            'type' => Transaction::TYPE_SIGNUP_BONUS,
        ]);
    }

    public function test_referral_cookie_attributes_new_user_to_referrer(): void
    {
        $referrer = User::factory()->create();

        $response = $this->withCookie(CaptureReferralCode::COOKIE, $referrer->referral_code)
            ->post('/register', [
                'email' => 'referee@example.com',
                'password' => self::VALID_PASSWORD,
                'password_confirmation' => self::VALID_PASSWORD,
            ]);

        $referee = User::where('email', 'referee@example.com')->firstOrFail();

        $this->assertSame($referrer->id, $referee->referred_by_id);
        $response->assertCookieExpired(CaptureReferralCode::COOKIE);
    }

    public function test_unknown_referral_code_does_not_block_registration(): void
    {
        $this->withCookie(CaptureReferralCode::COOKIE, 'NOPE1234')
            ->post('/register', [
                'email' => 'orphan@example.com',
                'password' => self::VALID_PASSWORD,
                'password_confirmation' => self::VALID_PASSWORD,
            ]);

        $user = User::where('email', 'orphan@example.com')->firstOrFail();

        $this->assertNull($user->referred_by_id);
    }

    public function test_referral_query_parameter_sets_cookie(): void
    {
        $response = $this->get('/?ref=ABCDEF12');

        $response->assertCookie(CaptureReferralCode::COOKIE, 'ABCDEF12');
    }

    public function test_empty_fields_show_unified_error_message(): void
    {
        $response = $this->from('/register')->post('/register', [
            'email' => '',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors([
            'email' => 'Заполните все поля',
            'password' => 'Заполните все поля',
        ]);
        $this->assertGuest();
    }

    public function test_invalid_email_format_is_rejected(): void
    {
        $response = $this->from('/register')->post('/register', [
            'email' => 'not-an-email',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        $errors = session('errors')->getBag('default');
        $this->assertNotSame('Заполните все поля', $errors->first('email'));
    }

    public function test_password_must_be_at_least_eight_characters(): void
    {
        $response = $this->from('/register')->post('/register', [
            'email' => 'shortpw@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('password');
        $this->assertGuest();

        $errors = session('errors')->getBag('default');
        $this->assertNotSame('Заполните все поля', $errors->first('password'));
    }

    public function test_registration_form_prefills_referral_code_from_cookie(): void
    {
        $response = $this->withCookie(CaptureReferralCode::COOKIE, 'ABCD1234')
            ->get('/register');

        $response->assertOk();
        $response->assertSee('name="referral_code"', false);
        $response->assertSee('value="ABCD1234"', false);
    }

    public function test_manual_referral_code_is_used_when_submitted(): void
    {
        $referrer = User::factory()->create();

        $this->post('/register', [
            'email' => 'manual-ref@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
            'referral_code' => $referrer->referral_code,
        ]);

        $referee = User::where('email', 'manual-ref@example.com')->firstOrFail();

        $this->assertSame($referrer->id, $referee->referred_by_id);
    }

    public function test_password_must_contain_uppercase_number_and_symbol(): void
    {
        $response = $this->from('/register')->post('/register', [
            'email' => 'weak-password@example.com',
            'password' => 'lowercaseonly',
            'password_confirmation' => 'lowercaseonly',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_password_confirmation_must_match(): void
    {
        $response = $this->from('/register')->post('/register', [
            'email' => 'mismatch@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => 'OtherPass1!',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'duplicate@example.com']);

        $response = $this->from('/register')->post('/register', [
            'email' => 'duplicate@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}

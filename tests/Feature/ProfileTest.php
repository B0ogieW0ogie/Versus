<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_settings_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile/settings')
            ->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile/settings', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile/settings');

        $user->refresh();
        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_email_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile/settings', [
                'name' => 'Test User',
                'email' => $user->email,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile/settings');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete('/profile/settings', ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_wrong_password_prevents_account_deletion(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/profile/settings')
            ->delete('/profile/settings', ['password' => 'wrong-password'])
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile/settings');

        $this->assertNotNull($user->fresh());
    }
}

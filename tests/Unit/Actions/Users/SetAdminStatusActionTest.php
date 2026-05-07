<?php

namespace Tests\Unit\Actions\Users;

use App\Actions\Users\SetAdminStatusAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetAdminStatusActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_grant_admin_role(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        app(SetAdminStatusAction::class)($user, true);

        $this->assertTrue($user->fresh()->is_admin);
    }

    public function test_it_can_revoke_admin_role(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        app(SetAdminStatusAction::class)($user, false);

        $this->assertFalse($user->fresh()->is_admin);
    }
}

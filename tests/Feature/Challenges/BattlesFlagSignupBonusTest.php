<?php

namespace Tests\Feature\Challenges;

use App\Actions\Users\CreditSignupBonusAction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattlesFlagSignupBonusTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_signup_bonus_when_battles_disabled(): void
    {
        config(['versus.battles_enabled' => false]);
        $user = User::factory()->create(['balance' => 0]);

        app(CreditSignupBonusAction::class)($user);

        $this->assertSame(0, Transaction::where('user_id', $user->id)->count());
        $this->assertSame('0.00', $user->fresh()->balance);
    }

    public function test_signup_bonus_still_credited_when_battles_enabled(): void
    {
        config(['versus.battles_enabled' => true]);
        $user = User::factory()->create(['balance' => 0]);

        app(CreditSignupBonusAction::class)($user);

        $this->assertSame(1, Transaction::where('user_id', $user->id)->count());
    }

    public function test_challenge_translations_exist_in_both_locales(): void
    {
        $en = require lang_path('en/challenges.php');
        $ru = require lang_path('ru/challenges.php');

        $this->assertSame([], array_diff(array_keys($en), array_keys($ru)));
        $this->assertSame([], array_diff(array_keys($ru), array_keys($en)));
    }
}

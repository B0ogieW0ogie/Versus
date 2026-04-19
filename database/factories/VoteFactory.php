<?php

namespace Database\Factories;

use App\Models\Battle;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vote>
 */
class VoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = $this->faker->randomFloat(2, 10, 500);

        return [
            'user_id' => User::factory(),
            'battle_id' => Battle::factory(),
            'side' => $this->faker->randomElement([Battle::SIDE_A, Battle::SIDE_B]),
            'amount' => $amount,
            'weight' => $amount,
            'referrer_id' => null,
            'payout' => null,
        ];
    }
}

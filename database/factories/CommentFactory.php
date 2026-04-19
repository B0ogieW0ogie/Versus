<?php

namespace Database\Factories;

use App\Models\Battle;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'battle_id' => Battle::factory(),
            'body' => $this->faker->paragraph(),
            'side' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Battle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Battle>
 */
class BattleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(4);

        return [
            'slug' => Str::slug($title).'-'.Str::random(6),
            'title' => $title,
            'description' => $this->faker->paragraph(),
            'side_a_label' => $this->faker->words(2, true),
            'side_b_label' => $this->faker->words(2, true),
            'side_a_image' => null,
            'side_b_image' => null,
            'status' => Battle::STATUS_ACTIVE,
            'opens_at' => now()->subMinute(),
            'closes_at' => now()->addDay(),
            'winning_side' => null,
            'total_pool' => 0,
            'created_by_id' => null,
            'settled_at' => null,
            'category_id' => null,
            'is_sponsored' => false,
            'sponsor_handle' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => Battle::STATUS_DRAFT]);
    }

    public function sponsored(string $handle = '@brand'): static
    {
        return $this->state(fn () => [
            'is_sponsored' => true,
            'sponsor_handle' => $handle,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => Battle::STATUS_CLOSED,
            'closes_at' => now()->subMinute(),
        ]);
    }

    public function settled(string $winningSide = Battle::SIDE_A): static
    {
        return $this->state(fn () => [
            'status' => Battle::STATUS_SETTLED,
            'closes_at' => now()->subHour(),
            'settled_at' => now(),
            'winning_side' => $winningSide,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Challenge>
 */
class ChallengeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $title = $this->faker->sentence(4);

        return [
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'user_id' => User::factory(),
            'title' => Str::limit($title, 100, ''),
            'rules' => $this->faker->sentence(12),
            'duration' => Challenge::DURATION_24H,
            'ends_at' => now()->addDay(),
            'status' => Challenge::STATUS_ACTIVE,
        ];
    }

    public function processing(): static
    {
        return $this->state(fn () => ['status' => Challenge::STATUS_PROCESSING, 'ends_at' => null]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => Challenge::STATUS_FAILED, 'ends_at' => null]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => Challenge::STATUS_CLOSED,
            'ends_at' => now()->subHour(),
            'closed_at' => now()->subMinutes(59),
        ]);
    }

    public function withOriginal(): static
    {
        return $this->afterCreating(function (Challenge $challenge): void {
            ChallengeEntry::factory()->original()->create([
                'challenge_id' => $challenge->id,
                'user_id' => $challenge->user_id,
                'status' => $challenge->status === Challenge::STATUS_PROCESSING
                    ? ChallengeEntry::STATUS_PROCESSING
                    : ChallengeEntry::STATUS_READY,
            ]);
        });
    }
}

<?php

namespace Database\Factories;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChallengeEntry>
 */
class ChallengeEntryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'challenge_id' => Challenge::factory(),
            'user_id' => User::factory(),
            'is_original' => false,
            'status' => ChallengeEntry::STATUS_READY,
            'video_path' => "videos/{$uuid}.mp4",
            'poster_path' => "posters/{$uuid}.jpg",
            'duration_ms' => 15000,
            'submitted_at' => now(),
        ];
    }

    public function original(): static
    {
        return $this->state(fn () => ['is_original' => true]);
    }

    public function processing(): static
    {
        return $this->state(fn () => [
            'status' => ChallengeEntry::STATUS_PROCESSING,
            'video_path' => null,
            'poster_path' => null,
            'duration_ms' => null,
            'source_path' => 'entries/pending/source',
        ]);
    }
}

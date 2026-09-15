<?php

namespace App\Actions\Challenges;

use App\Jobs\ProcessEntryVideo;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeParticipant;
use App\Models\User;
use App\Services\Video\UploadStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SubmitResponseAction
{
    public function __construct(private readonly UploadStore $uploads) {}

    public function __invoke(User $user, Challenge $challenge, string $uploadId): ChallengeEntry
    {
        $source = $this->uploads->claim($user, $uploadId);

        try {
            $entry = DB::transaction(function () use ($user, $challenge, $source): ChallengeEntry {
                /** @var Challenge $challenge */
                $challenge = Challenge::whereKey($challenge->id)->lockForUpdate()->firstOrFail();

                if (! $challenge->isOpen()) {
                    throw ValidationException::withMessages(['challenge' => __('challenges.not_open')]);
                }
                if ($challenge->user_id === $user->id) {
                    throw ValidationException::withMessages(['challenge' => __('challenges.own_challenge')]);
                }
                if (ChallengeEntry::where('challenge_id', $challenge->id)->where('user_id', $user->id)->exists()) {
                    throw ValidationException::withMessages(['challenge' => __('challenges.already_responded')]);
                }

                $entry = ChallengeEntry::create([
                    'challenge_id' => $challenge->id,
                    'user_id' => $user->id,
                    'is_original' => false,
                    'status' => ChallengeEntry::STATUS_PROCESSING,
                    'submitted_at' => now(),
                ]);

                $destination = "entries/{$entry->id}/source";
                Storage::disk('local')->move($source, $destination);
                $entry->update(['source_path' => $destination]);

                ChallengeParticipant::firstOrCreate(
                    ['user_id' => $user->id, 'challenge_id' => $challenge->id],
                    ['accepted_at' => now()],
                );

                ProcessEntryVideo::dispatch($entry->id)->afterCommit();

                return $entry;
            });
        } finally {
            $this->uploads->discard($uploadId);
        }

        return $entry;
    }
}

<?php

namespace App\Actions\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Notifications\ChallengePublished;
use App\Notifications\ChallengeResponseReceived;
use App\Notifications\DuelInvitation;
use App\Notifications\ResponsePublished;
use App\Services\Achievements\AwardAchievements;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MarkEntryReadyAction
{
    public function __construct(private readonly AwardAchievements $achievements) {}

    public function __invoke(ChallengeEntry $entry, string $videoPath, string $posterPath, int $durationMs): void
    {
        $result = DB::transaction(function () use ($entry, $videoPath, $posterPath, $durationMs): ?array {
            /** @var Challenge $challenge */
            $challenge = Challenge::whereKey($entry->challenge_id)->lockForUpdate()->firstOrFail();
            /** @var ChallengeEntry|null $entry */
            $entry = ChallengeEntry::whereKey($entry->id)->lockForUpdate()->first();

            if ($entry === null || $entry->status !== ChallengeEntry::STATUS_PROCESSING) {
                return null;
            }

            $sourcePath = $entry->source_path;
            $entry->fill([
                'status' => ChallengeEntry::STATUS_READY,
                'video_path' => $videoPath,
                'poster_path' => $posterPath,
                'duration_ms' => $durationMs,
                'source_path' => null,
            ])->save();

            if ($entry->is_original) {
                $challenge->status = Challenge::STATUS_ACTIVE;
                $challenge->ends_at = now()->addMinutes(Challenge::durationMinutes($challenge->duration));
                $challenge->save();
            } else {
                $challenge->increment('entries_count');
            }

            return [$challenge, $entry, $sourcePath];
        });

        if ($result === null) {
            return;
        }

        /** @var array{0: Challenge, 1: ChallengeEntry, 2: string|null} $result */
        [$challenge, $entry, $sourcePath] = $result;

        if ($sourcePath !== null) {
            Storage::disk('local')->deleteDirectory(dirname($sourcePath));
        }

        $this->achievements->check($entry->user, $entry->is_original ? AwardAchievements::KIND_CHALLENGES : AwardAchievements::KIND_RESPONSES);

        try {
            if ($entry->is_original) {
                $challenge->user->notify(new ChallengePublished($challenge));
                if ($challenge->isDuel()) {
                    $author = $challenge->user;
                    $challenge->opponent?->notify(new DuelInvitation($challenge, $author->username !== null ? '@'.$author->username : $author->name));
                }
            } else {
                $challenge->user->notify(new ChallengeResponseReceived($challenge, $entry, $entry->user));
                $entry->user->notify(new ResponsePublished($challenge, $entry));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}

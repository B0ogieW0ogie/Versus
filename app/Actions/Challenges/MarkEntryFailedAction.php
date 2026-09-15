<?php

namespace App\Actions\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\User;
use App\Notifications\EntryProcessingFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MarkEntryFailedAction
{
    public function __invoke(ChallengeEntry $entry, string $reasonKey): void
    {
        $result = DB::transaction(function () use ($entry, $reasonKey): ?array {
            /** @var Challenge $challenge */
            $challenge = Challenge::whereKey($entry->challenge_id)->lockForUpdate()->firstOrFail();
            /** @var ChallengeEntry|null $entry */
            $entry = ChallengeEntry::whereKey($entry->id)->lockForUpdate()->first();

            if ($entry === null || $entry->status !== ChallengeEntry::STATUS_PROCESSING) {
                return null;
            }

            $sourcePath = $entry->source_path;
            /** @var User $uploader */
            $uploader = $entry->user;

            if ($entry->is_original) {
                $entry->fill([
                    'status' => ChallengeEntry::STATUS_FAILED,
                    'failure_reason' => $reasonKey,
                    'source_path' => null,
                ])->save();
                $challenge->status = Challenge::STATUS_FAILED;
                $challenge->save();
            } else {
                // Deleting frees the unique (challenge_id, user_id) slot for a retry.
                $entry->delete();
            }

            return [$challenge, $uploader, $sourcePath];
        });

        if ($result === null) {
            return;
        }

        /** @var array{0: Challenge, 1: User, 2: string|null} $result */
        [$challenge, $uploader, $sourcePath] = $result;

        if ($sourcePath !== null) {
            Storage::disk('local')->deleteDirectory(dirname($sourcePath));
        }

        try {
            $uploader->notify(new EntryProcessingFailed($challenge, $reasonKey));
        } catch (Throwable $e) {
            report($e);
        }
    }
}

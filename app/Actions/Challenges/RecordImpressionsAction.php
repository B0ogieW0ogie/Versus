<?php

namespace App\Actions\Challenges;

use App\Models\ChallengeEntry;

class RecordImpressionsAction
{
    private const MAX_PER_BATCH = 20;

    /** @param array<int, mixed> $entryIds */
    public function __invoke(array $entryIds): void
    {
        $ids = collect($entryIds)
            ->filter(fn ($id): bool => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->take(self::MAX_PER_BATCH)
            ->values()
            ->all();

        if ($ids === []) {
            return;
        }

        ChallengeEntry::query()
            ->whereIn('id', $ids)
            ->where('status', ChallengeEntry::STATUS_READY)
            ->increment('impressions_count');
    }
}

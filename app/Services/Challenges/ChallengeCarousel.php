<?php

namespace App\Services\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ChallengeCarousel
{
    /** @return Collection<int, ChallengeEntry> */
    public function responses(Challenge $challenge, ?int $focusEntryId = null): Collection
    {
        $top = (int) config('versus.challenges.carousel_top_by_votes');
        $fresh = (int) config('versus.challenges.carousel_fresh_slots');

        $leaders = $top > 0
            ? $this->base($challenge)
                ->orderByDesc('votes_count')
                ->orderBy('submitted_at')
                ->orderBy('id')
                ->limit($top)
                ->get()
            : collect();

        $rotating = $fresh > 0
            ? $this->base($challenge)
                ->whereNotIn('id', $leaders->pluck('id')->all())
                ->orderBy('impressions_count')
                ->orderBy('id')
                ->limit($fresh * 2)
                ->get()
                ->shuffle()
                ->take($fresh)
            : collect();

        /** @var Collection<int, ChallengeEntry> $list */
        $list = collect($leaders->all())->concat($rotating->all())->values();

        if ($focusEntryId !== null && ! $list->contains('id', $focusEntryId)) {
            $focus = $this->base($challenge)->whereKey($focusEntryId)->first();
            if ($focus !== null) {
                $list = $list->prepend($focus)->values();
            }
        }

        return $list;
    }

    /** @return Builder<ChallengeEntry> */
    private function base(Challenge $challenge): Builder
    {
        return ChallengeEntry::query()
            ->where('challenge_id', $challenge->id)
            ->where('is_original', false)
            ->where('status', ChallengeEntry::STATUS_READY)
            ->with('user');
    }
}

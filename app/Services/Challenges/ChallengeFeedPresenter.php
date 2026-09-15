<?php

namespace App\Services\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeParticipant;
use App\Models\ChallengeVote;
use App\Models\Comment;
use App\Models\EntryLike;
use App\Models\User;

class ChallengeFeedPresenter
{
    public function __construct(private readonly ChallengeCarousel $carousel) {}

    /** @return array<string, mixed> */
    public function present(Challenge $challenge, ?User $viewer, ?int $focusEntryId = null): array
    {
        $challenge->loadMissing(['user', 'original.user', 'winnerEntry.user']);

        /** @var list<ChallengeEntry> $entries */
        $entries = array_values(array_filter([
            $challenge->original,
            ...$this->carousel->responses($challenge, $focusEntryId)->all(),
        ]));
        $ids = array_map(fn (ChallengeEntry $e): int => $e->id, $entries);

        $likedIds = $viewer !== null
            ? EntryLike::where('user_id', $viewer->id)->whereIn('entry_id', $ids)->pluck('entry_id')->all()
            : [];
        $commentCounts = Comment::query()
            ->whereIn('challenge_entry_id', $ids)
            ->selectRaw('challenge_entry_id, COUNT(*) as aggregate')
            ->groupBy('challenge_entry_id')
            ->pluck('aggregate', 'challenge_entry_id');

        $myEntryId = $viewer !== null
            ? ChallengeEntry::where('challenge_id', $challenge->id)->where('user_id', $viewer->id)->value('id')
            : null;
        $myVoteEntryId = $viewer !== null
            ? ChallengeVote::where('challenge_id', $challenge->id)->where('user_id', $viewer->id)->value('entry_id')
            : null;
        $accepted = $viewer !== null
            && ChallengeParticipant::where('challenge_id', $challenge->id)->where('user_id', $viewer->id)->exists();

        $slides = array_map(fn (ChallengeEntry $entry): array => [
            'entry_id' => $entry->id,
            'is_original' => $entry->is_original,
            'video_url' => $entry->videoUrl(),
            'poster_url' => $entry->posterUrl(),
            'author' => [
                'name' => $entry->user->name,
                'username' => $entry->user->username,
                'avatar_url' => $entry->user->avatarUrl(),
                'profile_url' => route('profile.show', $entry->user),
            ],
            'likes_count' => $entry->likes_count,
            'liked' => in_array($entry->id, $likedIds, true),
            'votes_count' => $entry->votes_count,
            'comments_count' => (int) ($commentCounts[$entry->id] ?? 0),
            'is_mine' => $viewer !== null && $entry->user_id === $viewer->id,
            'is_winner' => $challenge->winner_entry_id === $entry->id,
            'share_url' => route('challenges.show', ['challenge' => $challenge->slug, 'entry' => $entry->id]),
        ], $entries);

        $focusIndex = $focusEntryId !== null ? array_search($focusEntryId, $ids, true) : false;

        return [
            'id' => $challenge->id,
            'slug' => $challenge->slug,
            'title' => $challenge->title,
            'rules' => $challenge->rules,
            'author_name' => $challenge->user->name,
            'ends_at' => $challenge->ends_at?->toIso8601String(),
            'is_open' => $challenge->isOpen(),
            'is_own' => $viewer !== null && $challenge->user_id === $viewer->id,
            'accepted' => $accepted,
            'my_entry_id' => $myEntryId !== null ? (int) $myEntryId : null,
            'my_vote_entry_id' => $myVoteEntryId !== null ? (int) $myVoteEntryId : null,
            'entries_count' => $challenge->entries_count,
            'winner_entry_id' => $challenge->winner_entry_id,
            'winner_name' => $challenge->winnerEntry?->user?->name,
            'respond_url' => route('challenges.respond', $challenge->slug),
            'focus_index' => $focusIndex === false ? 0 : $focusIndex,
            'slides' => $slides,
        ];
    }
}

<?php

namespace App\Services\News;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeParticipant;
use App\Models\ChallengeVote;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Home news feed: what people do with Challenges. Only public (active/closed) challenges appear. */
class NewsFeedService
{
    public const SCOPE_ALL = 'all';

    public const SCOPE_FOLLOWING = 'following';

    private const PODIUM_SIZE = 3;

    /** @return Collection<int, NewsEvent> */
    public function events(?User $viewer, string $scope = self::SCOPE_ALL, ?string $type = null, int $limit = 15): Collection
    {
        $actorIds = $scope === self::SCOPE_FOLLOWING && $viewer !== null
            ? $viewer->following()->pluck('users.id')->all()
            : null;

        $types = $type !== null && in_array($type, NewsEvent::TYPES, true) ? [$type] : NewsEvent::TYPES;

        /** @var Collection<int, NewsEvent> $events */
        $events = collect();
        foreach ($types as $t) {
            $events = $events->concat(match ($t) {
                NewsEvent::TYPE_CREATED => $this->created($actorIds, $limit),
                NewsEvent::TYPE_ACCEPTED => $this->accepted($actorIds, $limit),
                NewsEvent::TYPE_RESPONDED => $this->responded($actorIds, $limit),
                NewsEvent::TYPE_VOTED => $this->voted($actorIds, $limit),
                // System events are not tied to people you follow.
                default => $actorIds === null ? $this->results($limit) : collect(),
            });
        }

        return $events
            ->sortByDesc(fn (NewsEvent $e) => $e->occurredAt->getTimestamp())
            ->take($limit)
            ->values();
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<int>|null  $actorIds
     * @return Builder<TModel>
     */
    private function publicOnly(Builder $query, ?array $actorIds, string $userColumn = 'user_id'): Builder
    {
        if ($actorIds !== null) {
            $query->whereIn($userColumn, $actorIds);
        }

        return $query->whereHas('challenge', fn (Builder $q) => $q->whereIn('status', [Challenge::STATUS_ACTIVE, Challenge::STATUS_CLOSED]));
    }

    /**
     * @param  array<int>|null  $actorIds
     * @return Collection<int, NewsEvent>
     */
    private function created(?array $actorIds, int $limit): Collection
    {
        $query = Challenge::query()
            ->whereIn('status', [Challenge::STATUS_ACTIVE, Challenge::STATUS_CLOSED])
            ->with(['user', 'original']);
        if ($actorIds !== null) {
            $query->whereIn('user_id', $actorIds);
        }

        return $query->orderByDesc('created_at')->limit($limit)->get()
            ->map(fn (Challenge $c) => new NewsEvent(NewsEvent::TYPE_CREATED, $c->user, $c, $c->created_at))
            ->values();
    }

    /**
     * @param  array<int>|null  $actorIds
     * @return Collection<int, NewsEvent>
     */
    private function accepted(?array $actorIds, int $limit): Collection
    {
        return $this->publicOnly(ChallengeParticipant::query(), $actorIds)
            ->with(['user', 'challenge.original'])
            ->orderByDesc('accepted_at')
            ->limit($limit)
            ->get()
            ->map(fn (ChallengeParticipant $p) => new NewsEvent(NewsEvent::TYPE_ACCEPTED, $p->user, $p->challenge, $p->accepted_at))
            ->values();
    }

    /**
     * @param  array<int>|null  $actorIds
     * @return Collection<int, NewsEvent>
     */
    private function responded(?array $actorIds, int $limit): Collection
    {
        return $this->publicOnly(ChallengeEntry::query(), $actorIds)
            ->where('is_original', false)
            ->where('status', ChallengeEntry::STATUS_READY)
            ->with(['user', 'challenge'])
            ->orderByDesc('submitted_at')
            ->limit($limit)
            ->get()
            ->map(fn (ChallengeEntry $e) => new NewsEvent(NewsEvent::TYPE_RESPONDED, $e->user, $e->challenge, $e->submitted_at, $e))
            ->values();
    }

    /**
     * @param  array<int>|null  $actorIds
     * @return Collection<int, NewsEvent>
     */
    private function voted(?array $actorIds, int $limit): Collection
    {
        return $this->publicOnly(ChallengeVote::query(), $actorIds)
            ->with(['user', 'challenge.original'])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (ChallengeVote $v) => new NewsEvent(NewsEvent::TYPE_VOTED, $v->user, $v->challenge, $v->updated_at ?? $v->created_at ?? now()))
            ->values();
    }

    /** @return Collection<int, NewsEvent> */
    private function results(int $limit): Collection
    {
        $challenges = Challenge::query()
            ->where('status', Challenge::STATUS_CLOSED)
            ->whereNotNull('closed_at')
            ->with('original')
            ->orderByDesc('closed_at')
            ->limit($limit)
            ->get();

        $podiums = ChallengeEntry::query()
            ->whereIn('challenge_id', $challenges->modelKeys())
            ->where('status', ChallengeEntry::STATUS_READY)
            ->where('votes_count', '>', 0)
            ->with('user')
            ->orderByDesc('votes_count')
            ->orderBy('submitted_at')
            ->get()
            ->groupBy('challenge_id');

        return $challenges
            ->map(fn (Challenge $c) => new NewsEvent(
                NewsEvent::TYPE_RESULTS,
                null,
                $c,
                $c->closed_at,
                null,
                array_values($podiums->get($c->id, collect())->take(self::PODIUM_SIZE)->all()),
            ))
            ->values();
    }
}

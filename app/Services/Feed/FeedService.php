<?php

namespace App\Services\Feed;

use App\Models\Battle;
use App\Models\Comment;
use App\Models\User;
use App\Models\Vote;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FeedService
{
    public const FILTER_ALL = 'all';

    public const FILTER_VOTES = 'votes';

    public const FILTER_ARGUMENTS = 'arguments';

    public const FILTER_CREATED = 'created';

    public const FILTER_RESULTS = 'results';

    /**
     * @return Collection<int, FeedEvent>
     */
    public function events(User $viewer, string $filter = self::FILTER_ALL, ?CarbonInterface $before = null, int $limit = 15): Collection
    {
        $viewerId = (int) $viewer->getKey();
        $actorIds = $this->actorIds($viewer);

        /** @var Collection<int, FeedEvent> $events */
        $events = collect();

        if (in_array($filter, [self::FILTER_ALL, self::FILTER_CREATED], true)) {
            $events = $events->concat($this->createEvents($actorIds, $viewerId, $before, $limit));
        }
        if (in_array($filter, [self::FILTER_ALL, self::FILTER_VOTES], true)) {
            $events = $events->concat($this->voteEvents($actorIds, $viewerId, $before, $limit));
        }
        if (in_array($filter, [self::FILTER_ALL, self::FILTER_ARGUMENTS], true)) {
            $events = $events->concat($this->argueEvents($actorIds, $viewerId, $before, $limit));
        }
        if (in_array($filter, [self::FILTER_ALL, self::FILTER_RESULTS], true)) {
            $events = $events->concat($this->resultEvents($actorIds, $viewerId, $before, $limit));
        }

        $events = $events->sortByDesc(fn (FeedEvent $e) => $e->occurredAt->getTimestamp())->values();

        return $events->take($limit)->values();
    }

    /**
     * Followed-user ids, or null to mean "global" (everyone but the viewer).
     *
     * @return array<int>|null
     */
    private function actorIds(User $viewer): ?array
    {
        /** @var array<int> $ids */
        $ids = $viewer->following()->pluck('users.id')->all();

        return count($ids) > 0 ? $ids : null;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<int>|null  $actorIds
     * @return Builder<TModel>
     */
    private function applyActor(Builder $query, ?array $actorIds, string $column, int $viewerId): Builder
    {
        if ($actorIds === null) {
            return $query->where($column, '!=', $viewerId);
        }

        return $query->whereIn($column, $actorIds);
    }

    /**
     * @param  array<int>|null  $actorIds
     * @return Collection<int, FeedEvent>
     */
    private function createEvents(?array $actorIds, int $viewerId, ?CarbonInterface $before, int $limit): Collection
    {
        $query = Battle::query()
            ->whereNotNull('created_by_id')
            ->whereIn('status', [Battle::STATUS_ACTIVE, Battle::STATUS_CLOSED, Battle::STATUS_SETTLED])
            ->with(['creator', 'category']);

        $query = $this->applyActor($query, $actorIds, 'created_by_id', $viewerId);

        if ($before !== null) {
            $query->where('created_at', '<', $before);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Battle> $battles */
        $battles = $query->orderByDesc('created_at')->limit($limit)->get();

        return $battles
            ->filter(fn (Battle $b) => $b->creator !== null)
            ->map(fn (Battle $b) => new FeedEvent(FeedEvent::TYPE_CREATE, $b->creator, $b, $b->created_at))
            ->values();
    }

    /**
     * @param  array<int>|null  $actorIds
     * @return Collection<int, FeedEvent>
     */
    private function voteEvents(?array $actorIds, int $viewerId, ?CarbonInterface $before, int $limit): Collection
    {
        $query = Vote::query()->with(['user', 'battle.category']);
        $query = $this->applyActor($query, $actorIds, 'user_id', $viewerId);

        if ($before !== null) {
            $query->where('created_at', '<', $before);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Vote> $votes */
        $votes = $query->orderByDesc('created_at')->limit($limit)->get();

        return $votes
            ->filter(fn (Vote $v) => $v->user !== null && $v->battle !== null)
            ->map(fn (Vote $v) => new FeedEvent(FeedEvent::TYPE_VOTE, $v->user, $v->battle, $v->created_at))
            ->values();
    }

    /**
     * @param  array<int>|null  $actorIds
     * @return Collection<int, FeedEvent>
     */
    private function argueEvents(?array $actorIds, int $viewerId, ?CarbonInterface $before, int $limit): Collection
    {
        $query = Comment::query()->with(['user', 'battle.category']);
        $query = $this->applyActor($query, $actorIds, 'user_id', $viewerId);

        if ($before !== null) {
            $query->where('created_at', '<', $before);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Comment> $comments */
        $comments = $query->orderByDesc('created_at')->limit($limit)->get();

        return $comments
            ->filter(fn (Comment $c) => $c->user !== null && $c->battle !== null)
            ->map(fn (Comment $c) => new FeedEvent(FeedEvent::TYPE_ARGUE, $c->user, $c->battle, $c->created_at, $c->body))
            ->values();
    }

    /**
     * @param  array<int>|null  $actorIds
     * @return Collection<int, FeedEvent>
     */
    private function resultEvents(?array $actorIds, int $viewerId, ?CarbonInterface $before, int $limit): Collection
    {
        $query = Vote::query()
            ->select('votes.*')
            ->join('battles', 'battles.id', '=', 'votes.battle_id')
            ->where('battles.status', Battle::STATUS_SETTLED)
            ->whereNotNull('battles.winning_side')
            ->with(['user', 'battle.category']);

        if ($before !== null) {
            $query->where('battles.settled_at', '<', $before);
        }

        $query = $this->applyActor($query, $actorIds, 'votes.user_id', $viewerId);

        // Over-fetch (anchored to recency) since many votes collapse into one result per (user, battle).
        $votes = $query->orderByDesc('battles.settled_at')->limit($limit * 5)->get()
            ->filter(fn (Vote $v) => $v->user !== null && $v->battle !== null);

        return $votes
            ->groupBy(fn (Vote $v) => $v->user_id.':'.$v->battle_id)
            ->map(function (Collection $group) {
                /** @var Vote $first */
                $first = $group->first();
                $battle = $first->battle;
                $won = $group->contains(fn (Vote $v) => $v->side === $battle->winning_side);

                return new FeedEvent(
                    $won ? FeedEvent::TYPE_WIN : FeedEvent::TYPE_LOSE,
                    $first->user,
                    $battle,
                    $battle->settled_at,
                );
            })
            ->sortByDesc(fn (FeedEvent $e) => $e->occurredAt->getTimestamp())
            ->take($limit)
            ->values();
    }
}

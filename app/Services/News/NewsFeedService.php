<?php

namespace App\Services\News;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeParticipant;
use App\Models\ChallengeVote;
use App\Models\FeedPost;
use App\Models\User;
use App\Services\Recommendations\RecommendedProfiles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Home News Feed: a silent, strictly chronological social chronicle.
 *
 * Sources: people the viewer follows, people the platform recommends ("interesting"), and VERSUS
 * itself (challenge results, official news). "All" is the union of the three.
 */
class NewsFeedService
{
    public const SOURCE_ALL = 'all';

    public const SOURCE_FOLLOWING = 'following';

    public const SOURCE_INTERESTING = 'interesting';

    public const SOURCES = [self::SOURCE_ALL, self::SOURCE_FOLLOWING, self::SOURCE_INTERESTING];

    /** Filter id => event types it shows. */
    public const FILTERS = [
        'challenges' => [NewsEvent::TYPE_CREATED, NewsEvent::TYPE_ACCEPTED],
        'responses' => [NewsEvent::TYPE_RESPONDED],
        'votes' => [NewsEvent::TYPE_VOTED],
        'results' => [NewsEvent::TYPE_RESULTS],
        'top' => [NewsEvent::TYPE_TOP_CHANGE],
        'statuses' => [NewsEvent::TYPE_STATUS],
        'achievements' => [NewsEvent::TYPE_ACHIEVEMENT],
        'versus_news' => [NewsEvent::TYPE_VERSUS_NEWS],
    ];

    private const PODIUM_SIZE = 3;

    private const PUBLIC_STATUSES = [Challenge::STATUS_ACTIVE, Challenge::STATUS_CLOSED];

    public function __construct(private readonly RecommendedProfiles $recommended) {}

    /** @return Collection<int, NewsEvent> newest first */
    public function events(?User $viewer, string $source = self::SOURCE_ALL, ?string $filter = null, int $limit = 15): Collection
    {
        $source = in_array($source, self::SOURCES, true) ? $source : self::SOURCE_ALL;
        $types = self::FILTERS[$filter ?? ''] ?? array_merge(...array_values(self::FILTERS));
        $actorIds = $this->actorIds($viewer, $source);
        $withSystem = $source === self::SOURCE_ALL;

        /** @var Collection<int, NewsEvent> $events */
        $events = collect();
        $want = fn (string $type): bool => in_array($type, $types, true);

        if ($actorIds !== []) {
            if ($want(NewsEvent::TYPE_CREATED)) {
                $events = $events->concat($this->created($actorIds, $limit));
            }
            if ($want(NewsEvent::TYPE_ACCEPTED)) {
                $events = $events->concat($this->accepted($actorIds, $limit));
            }
            if ($want(NewsEvent::TYPE_RESPONDED)) {
                $events = $events->concat($this->responded($actorIds, $limit));
            }
            if ($want(NewsEvent::TYPE_VOTED)) {
                $events = $events->concat($this->voted($actorIds, $limit));
            }
        }
        if ($withSystem && $want(NewsEvent::TYPE_RESULTS)) {
            $events = $events->concat($this->results($limit));
        }

        $postTypes = array_values(array_filter(
            [NewsEvent::TYPE_TOP_CHANGE, NewsEvent::TYPE_STATUS, NewsEvent::TYPE_ACHIEVEMENT, NewsEvent::TYPE_VERSUS_NEWS],
            $want,
        ));
        $events = $events->concat($this->posts($postTypes, $actorIds, $withSystem, $limit));

        return $events
            ->sortByDesc(fn (NewsEvent $e) => $e->occurredAt->getTimestamp())
            ->take($limit)
            ->values();
    }

    /** @return list<int> the people whose actions this source shows (never the viewer) */
    private function actorIds(?User $viewer, string $source): array
    {
        $following = in_array($source, [self::SOURCE_ALL, self::SOURCE_FOLLOWING], true) && $viewer !== null
            ? $viewer->following()->pluck('users.id')->map(fn ($id): int => (int) $id)->all()
            : [];
        $interesting = in_array($source, [self::SOURCE_ALL, self::SOURCE_INTERESTING], true)
            ? $this->recommended->userIds($viewer)
            : [];

        return array_values(array_diff(array_unique(array_merge($following, $interesting)), [$viewer?->id]));
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function onPublicChallenge(Builder $query): Builder
    {
        return $query->whereHas('challenge', fn (Builder $q) => $q->whereIn('status', self::PUBLIC_STATUSES));
    }

    /**
     * @param  list<int>  $actorIds
     * @return Collection<int, NewsEvent>
     */
    private function created(array $actorIds, int $limit): Collection
    {
        return Challenge::query()
            ->whereIn('status', self::PUBLIC_STATUSES)
            ->whereIn('user_id', $actorIds)
            ->with(['user', 'original'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Challenge $c) => new NewsEvent(NewsEvent::TYPE_CREATED, $c->user, $c->created_at, $c))
            ->values();
    }

    /**
     * @param  list<int>  $actorIds
     * @return Collection<int, NewsEvent>
     */
    private function accepted(array $actorIds, int $limit): Collection
    {
        return $this->onPublicChallenge(ChallengeParticipant::query())
            ->whereIn('user_id', $actorIds)
            ->with(['user', 'challenge.original'])
            ->orderByDesc('accepted_at')
            ->limit($limit)
            ->get()
            ->map(fn (ChallengeParticipant $p) => new NewsEvent(NewsEvent::TYPE_ACCEPTED, $p->user, $p->accepted_at, $p->challenge))
            ->values();
    }

    /**
     * @param  list<int>  $actorIds
     * @return Collection<int, NewsEvent>
     */
    private function responded(array $actorIds, int $limit): Collection
    {
        return $this->onPublicChallenge(ChallengeEntry::query())
            ->whereIn('user_id', $actorIds)
            ->where('is_original', false)
            ->where('status', ChallengeEntry::STATUS_READY)
            ->with(['user', 'challenge'])
            ->orderByDesc('submitted_at')
            ->limit($limit)
            ->get()
            ->map(fn (ChallengeEntry $e) => new NewsEvent(NewsEvent::TYPE_RESPONDED, $e->user, $e->submitted_at, $e->challenge, $e))
            ->values();
    }

    /**
     * @param  list<int>  $actorIds
     * @return Collection<int, NewsEvent>
     */
    private function voted(array $actorIds, int $limit): Collection
    {
        // Which video got the vote is not shown: the row points at the challenge.
        return $this->onPublicChallenge(ChallengeVote::query())
            ->whereIn('user_id', $actorIds)
            ->with(['user', 'challenge.original'])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (ChallengeVote $v) => new NewsEvent(NewsEvent::TYPE_VOTED, $v->user, $v->updated_at ?? $v->created_at ?? now(), $v->challenge))
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
                $c->closed_at,
                $c,
                null,
                array_values($podiums->get($c->id, collect())->take(self::PODIUM_SIZE)->all()),
            ))
            ->values();
    }

    /**
     * @param  list<string>  $types
     * @param  list<int>  $actorIds
     * @return Collection<int, NewsEvent>
     */
    private function posts(array $types, array $actorIds, bool $withSystem, int $limit): Collection
    {
        $userTypes = array_values(array_diff($types, [NewsEvent::TYPE_VERSUS_NEWS]));
        $official = $withSystem && in_array(NewsEvent::TYPE_VERSUS_NEWS, $types, true);

        if (($userTypes === [] || $actorIds === []) && ! $official) {
            return collect();
        }

        return FeedPost::query()
            ->where('published_at', '<=', now())
            ->where(function (Builder $q) use ($userTypes, $actorIds, $official): void {
                if ($userTypes !== [] && $actorIds !== []) {
                    $q->orWhere(fn (Builder $w) => $w->whereIn('type', $userTypes)->whereIn('user_id', $actorIds));
                }
                if ($official) {
                    $q->orWhere('type', FeedPost::TYPE_VERSUS_NEWS);
                }
            })
            // Top moves only count while their challenge is public.
            ->where(fn (Builder $q) => $q->whereNull('challenge_id')->orWhereHas('challenge', fn (Builder $c) => $c->whereIn('status', self::PUBLIC_STATUSES)))
            ->with(['user', 'challenge', 'entry'])
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (FeedPost $p) => new NewsEvent($p->type, $p->user, $p->published_at, $p->challenge, $p->entry, [], $p))
            ->values();
    }
}

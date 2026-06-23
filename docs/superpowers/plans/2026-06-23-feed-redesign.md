# Feed Page Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rework the `/feed` page so each item is a one-sentence user-action card, the list spans the full header width, and the battle banner shows the live pool plus WINNER/LOSER outcomes for finished battles.

**Architecture:** Feed items are `FeedEvent` value objects assembled by `FeedService` from `Battle`/`Vote`/`Comment`/`CommentLike` rows. We drop the vote+argue grouping (each event is its own card), add a `like` event type, attach the chosen side label to vote events, and rebuild two Blade components (`event-card`, `battle-mini-card`) plus the page layout.

**Tech Stack:** Laravel 13, Livewire 4, Blade, Tailwind, Pest 4, PostgreSQL (dev) / SQLite `:memory:` (tests).

## Global Constraints

- Run all dev commands through the workspace container via the Makefile (`make ws`, `make test`, `make pint`, `make stan`). Never invoke `php`/`composer`/`npm`/`artisan` on the host.
- Tests use SQLite `:memory:`; test classes opt in with `uses(RefreshDatabase::class)`. Avoid Postgres-only SQL.
- i18n: every user-facing string must exist in **both** `lang/en/` and `lang/ru/`. Use `__('...')` keys, never inline literals.
- Larastan runs at level 6; run it with a raised memory limit (`make stan` / `--memory-limit=512M`).
- Money/pool display uses `Battle::compactPool()`; do not inline pool formatting.
- Final gate before "done": `make pint && make stan && make test` must pass.

---

### Task 1: Feed service layer — drop grouping, add like + vote-side

**Files:**
- Modify: `app/Services/Feed/FeedEvent.php`
- Modify: `app/Services/Feed/FeedService.php`
- Test: `tests/Feature/Feed/FeedServiceTest.php`

**Interfaces:**
- Produces:
  - `FeedEvent::TYPE_LIKE = 'like'` constant; `FeedEvent::TYPE_VOTE_ARGUE` removed.
  - `FeedEvent` constructor: `__construct(string $type, User $actor, Battle $battle, CarbonInterface $occurredAt, ?string $argumentText = null, ?string $sideLabel = null)` — new readonly `?string $sideLabel` (the raw `side_a_label`/`side_b_label` chosen by a vote; null for non-vote events).
  - `FeedEvent::groupKey()` and `FeedEvent::isGroupable()` removed; `isOpen()` kept.
  - `FeedService::events(...)` no longer groups; `FILTER_ALL` additionally includes like events.
- Consumes: `App\Models\CommentLike` (`user`, `comment` relations; `comment->battle`, `comment->body`, `created_at`).

- [ ] **Step 1: Update the FeedServiceTest expectations (write the failing tests)**

Replace the two grouping-era tests and add three new ones. In `tests/Feature/Feed/FeedServiceTest.php`:

First, **delete** the test `vote and argue in the same battle merge into one card` (lines ~200-214) and the test `grouped feed still surfaces events past the window for heavy voters` (lines ~246-282).

Add `use App\Models\CommentLike;` to the top `use` block (after `use App\Models\Comment;`).

Then add these tests at the end of the file:

```php
test('vote and argue in the same battle are now two separate cards', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $viewer->following()->attach($author->id);

    $battle = Battle::factory()->create();
    Vote::factory()->create(['user_id' => $author->id, 'battle_id' => $battle->id]);
    Comment::factory()->create(['user_id' => $author->id, 'battle_id' => $battle->id, 'body' => 'Because reasons']);

    $events = feed($viewer);

    expect($events)->toHaveCount(2)
        ->and($events->pluck('type')->sort()->values()->all())
        ->toBe([FeedEvent::TYPE_ARGUE, FeedEvent::TYPE_VOTE]);
});

test('vote event carries the chosen side label', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $viewer->following()->attach($author->id);

    $battle = Battle::factory()->create(['side_a_label' => 'cats', 'side_b_label' => 'dogs']);
    Vote::factory()->create([
        'user_id' => $author->id,
        'battle_id' => $battle->id,
        'side' => Battle::SIDE_B,
    ]);

    $event = feed($viewer, FeedService::FILTER_VOTES)->first();

    expect($event->type)->toBe(FeedEvent::TYPE_VOTE)
        ->and($event->sideLabel)->toBe('dogs');
});

test('like event appears in the all feed with the liked comment text', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $viewer->following()->attach($author->id);

    $comment = Comment::factory()->create(['body' => 'There is no spoon']);
    CommentLike::create(['user_id' => $author->id, 'comment_id' => $comment->id]);

    $events = feed($viewer);

    $like = $events->firstWhere('type', FeedEvent::TYPE_LIKE);
    expect($like)->not->toBeNull()
        ->and($like->actor->id)->toBe($author->id)
        ->and($like->battle->id)->toBe($comment->battle_id)
        ->and($like->argumentText)->toBe('There is no spoon');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `make ws` then `vendor/bin/pest tests/Feature/Feed/FeedServiceTest.php`
Expected: FAIL — `TYPE_LIKE` / `sideLabel` undefined, like events absent, vote+argue still collapsing.

- [ ] **Step 3: Update FeedEvent**

Replace the body of `app/Services/Feed/FeedEvent.php` with:

```php
<?php

namespace App\Services\Feed;

use App\Models\Battle;
use App\Models\User;
use Carbon\CarbonInterface;

class FeedEvent
{
    public const TYPE_CREATE = 'create';

    public const TYPE_VOTE = 'vote';

    public const TYPE_ARGUE = 'argue';

    public const TYPE_LIKE = 'like';

    public const TYPE_WIN = 'win';

    public const TYPE_LOSE = 'lose';

    public function __construct(
        public readonly string $type,
        public readonly User $actor,
        public readonly Battle $battle,
        public readonly CarbonInterface $occurredAt,
        public readonly ?string $argumentText = null,
        public readonly ?string $sideLabel = null,
    ) {}

    public function isOpen(): bool
    {
        return $this->battle->isOpenForVoting();
    }
}
```

- [ ] **Step 4: Update FeedService — remove grouping, add likes, attach side label**

In `app/Services/Feed/FeedService.php`:

(a) Add `use App\Models\CommentLike;` after `use App\Models\Comment;`.

(b) **Delete** the `GROUP_FETCH_BUFFER` const and its doc block (lines ~25-32) and the entire `group()` method (lines ~70-109).

(c) Replace the `events()` method body with (note: no over-fetch buffer, no grouping, likes added to ALL):

```php
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
        if ($filter === self::FILTER_ALL) {
            $events = $events->concat($this->likeEvents($actorIds, $viewerId, $before, $limit));
        }

        return $events
            ->sortByDesc(fn (FeedEvent $e) => $e->occurredAt->getTimestamp())
            ->take($limit)
            ->values();
    }
```

(d) Replace the `voteEvents()` map so the chosen side label rides along:

```php
        return $votes
            ->filter(fn (Vote $v) => $v->user !== null && $v->battle !== null)
            ->map(fn (Vote $v) => new FeedEvent(
                FeedEvent::TYPE_VOTE,
                $v->user,
                $v->battle,
                $v->created_at,
                null,
                $v->side === Battle::SIDE_A ? $v->battle->side_a_label : $v->battle->side_b_label,
            ))
            ->values();
```

(e) Add a `likeEvents()` method (place it after `argueEvents()`):

```php
    /**
     * @param  array<int>|null  $actorIds
     * @return Collection<int, FeedEvent>
     */
    private function likeEvents(?array $actorIds, int $viewerId, ?CarbonInterface $before, int $limit): Collection
    {
        $query = CommentLike::query()->with(['user', 'comment.battle.category']);
        $query = $this->applyActor($query, $actorIds, 'user_id', $viewerId);

        if ($before !== null) {
            $query->where('created_at', '<', $before);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, CommentLike> $likes */
        $likes = $query->orderByDesc('created_at')->limit($limit)->get();

        return $likes
            ->filter(fn (CommentLike $l) => $l->user !== null && $l->comment !== null && $l->comment->battle !== null)
            ->map(fn (CommentLike $l) => new FeedEvent(
                FeedEvent::TYPE_LIKE,
                $l->user,
                $l->comment->battle,
                $l->created_at,
                $l->comment->body,
            ))
            ->values();
    }
```

- [ ] **Step 5: Run the full feed service suite to verify it passes**

Run: `make ws` then `vendor/bin/pest tests/Feature/Feed/FeedServiceTest.php`
Expected: PASS (all tests green, including the unchanged create/vote/argue/win/lose/cursor tests).

- [ ] **Step 6: Commit**

```bash
git add app/Services/Feed/FeedEvent.php app/Services/Feed/FeedService.php tests/Feature/Feed/FeedServiceTest.php
git commit -m "feat(feed): drop vote+argue grouping, add like events and vote side label"
```

---

### Task 2: Translations — event phrases, like, winner/loser badges

**Files:**
- Modify: `lang/en/feed.php`
- Modify: `lang/ru/feed.php`
- Modify: `lang/en/battle.php`
- Modify: `lang/ru/battle.php`

**Interfaces:**
- Produces translation keys consumed by Tasks 3 & 4:
  - `feed.event.created`, `feed.event.voted` (with `:side`), `feed.event.argued`, `feed.event.liked`, `feed.event.won`, `feed.event.lost` — each is the verb phrase **without** the user's name.
  - removed: `feed.event.vote_and_argue`, `feed.rank_placeholder`.
  - `battle.winner_badge`, `battle.loser_badge`.

- [ ] **Step 1: Update `lang/en/feed.php`**

Replace the `event` array and remove `rank_placeholder`. The file becomes:

```php
<?php

return [
    'filters' => [
        'all' => 'All',
        'votes' => 'Votes',
        'arguments' => 'Arguments',
        'created' => 'Created',
        'results' => 'Results',
    ],
    'event' => [
        'created' => 'created a battle',
        'voted' => 'voted for :side in the battle',
        'argued' => 'commented on the battle',
        'liked' => 'liked an argument',
        'won' => 'won the battle',
        'lost' => 'lost the battle',
    ],
    'cta' => [
        'vote_with' => 'VOTE WITH',
        'vote_with_user' => 'VOTE WITH :user',
        'view_battle' => 'VIEW BATTLE',
        'battle_ended' => 'BATTLE ENDED',
    ],
    'load_more' => 'Load more',
    'empty' => [
        'title' => 'Your feed is quiet',
        'body' => 'Follow people to see their battles, votes and arguments here.',
    ],
];
```

- [ ] **Step 2: Update `lang/ru/feed.php`**

```php
<?php

return [
    'filters' => [
        'all' => 'Все',
        'votes' => 'Голоса',
        'arguments' => 'Аргументы',
        'created' => 'Созданные',
        'results' => 'Результаты',
    ],
    'event' => [
        'created' => 'создал баттл',
        'voted' => 'проголосовал за :side в баттле',
        'argued' => 'прокомментировал баттл',
        'liked' => 'лайкнул аргумент',
        'won' => 'выиграл баттл',
        'lost' => 'проиграл баттл',
    ],
    'cta' => [
        'vote_with' => 'ГОЛОСОВАТЬ С НИМ',
        'vote_with_user' => 'ГОЛОСОВАТЬ С :user',
        'view_battle' => 'СМОТРЕТЬ БАТТЛ',
        'battle_ended' => 'БАТТЛ ЗАВЕРШЁН',
    ],
    'load_more' => 'Загрузить ещё',
    'empty' => [
        'title' => 'В ленте пока тихо',
        'body' => 'Подписывайтесь на людей, чтобы видеть их баттлы, голоса и аргументы.',
    ],
];
```

- [ ] **Step 3: Add WINNER/LOSER badge keys to battle lang files**

In `lang/en/battle.php`, add after the existing `'winner' => 'Winner',` line:

```php
    'winner_badge' => 'WINNER',
    'loser_badge' => 'LOSER',
```

In `lang/ru/battle.php`, add after the existing `'winner' => 'Победитель',` line:

```php
    'winner_badge' => 'ПОБЕДИТЕЛЬ',
    'loser_badge' => 'ПРОИГРАВШИЙ',
```

- [ ] **Step 4: Sanity-check the lang files parse**

Run: `make ws` then `php -r "require 'lang/en/feed.php'; require 'lang/ru/feed.php'; require 'lang/en/battle.php'; require 'lang/ru/battle.php'; echo 'ok'.PHP_EOL;"`
Expected: prints `ok` (no parse errors).

- [ ] **Step 5: Commit**

```bash
git add lang/en/feed.php lang/ru/feed.php lang/en/battle.php lang/ru/battle.php
git commit -m "i18n(feed): subject-less event phrases, like phrase, winner/loser badges"
```

---

### Task 3: Event card — clickable name, sentence row, no rank/handle header

**Files:**
- Modify: `resources/views/components/feed/event-card.blade.php`
- Test: `tests/Feature/Feed/BattleMiniCardTest.php`

**Interfaces:**
- Consumes: `FeedEvent` (`type`, `actor`, `battle`, `argumentText`, `sideLabel`, `isOpen()`), the `feed.event.*` / `feed.cta.*` keys from Task 2, `route('profile.show', $actor)`.
- Produces: a card whose first line is `<a>{username-without-@}</a> {verb phrase}`.

- [ ] **Step 1: Update the event-card tests (write the failing tests)**

In `tests/Feature/Feed/BattleMiniCardTest.php`:

Replace the test `event card renders a win headline and view-battle CTA` (lines ~45-62) with:

```php
test('event card renders a win headline with a clickable name and no @', function () {
    app()->setLocale('en');

    $actor = User::factory()->create(['username' => 'neo', 'name' => 'Neo']);
    $battle = Battle::factory()->settled(Battle::SIDE_A)->create();
    $event = new FeedEvent(
        FeedEvent::TYPE_WIN,
        $actor,
        $battle,
        $battle->settled_at,
    );

    $html = Blade::render('<x-feed.event-card :event="$event" />', ['event' => $event]);

    expect($html)->toContain('neo')
        ->toContain('won the battle')
        ->not->toContain('@neo')
        ->toContain(route('profile.show', $actor))
        ->toContain('VIEW BATTLE');
});
```

Replace the test `event card renders an argument quote for a grouped vote+argue` (lines ~64-82) with a like-event test:

```php
test('event card renders a like headline with the argument quote', function () {
    app()->setLocale('en');

    $actor = User::factory()->create(['username' => 'trinity', 'name' => 'Trinity']);
    $battle = Battle::factory()->create();
    $event = new FeedEvent(
        FeedEvent::TYPE_LIKE,
        $actor,
        $battle,
        now(),
        'There is no spoon',
    );

    $html = Blade::render('<x-feed.event-card :event="$event" />', ['event' => $event]);

    expect($html)->toContain('liked an argument')
        ->toContain('There is no spoon');
});
```

Add a vote-side test at the end of the file:

```php
test('event card names the chosen side in a vote headline', function () {
    app()->setLocale('en');

    $actor = User::factory()->create(['username' => 'cypher', 'name' => 'Cypher']);
    $battle = Battle::factory()->create(['side_a_label' => 'cats', 'side_b_label' => 'dogs']);
    $event = new FeedEvent(
        FeedEvent::TYPE_VOTE,
        $actor,
        $battle,
        now(),
        null,
        'dogs',
    );

    $html = Blade::render('<x-feed.event-card :event="$event" />', ['event' => $event]);

    expect($html)->toContain('voted for Dogs in the battle');
});
```

Also update the existing `event card shows battle ended for a vote on a closed battle` test: it stays valid, but change its `@cypher`-free assertions are already fine — leave it as is.

- [ ] **Step 2: Run the event-card tests to verify they fail**

Run: `make ws` then `vendor/bin/pest tests/Feature/Feed/BattleMiniCardTest.php`
Expected: FAIL — card still prints `@neo`, uses old phrases, `TYPE_VOTE_ARGUE` reference may error.

- [ ] **Step 3: Rewrite the event-card component**

Replace the entire contents of `resources/views/components/feed/event-card.blade.php` with:

```blade
@props(['event'])

@php
    use App\Services\Feed\FeedEvent;
    use Illuminate\Support\Str;

    /** @var FeedEvent $event */
    $actor = $event->actor;
    $battle = $event->battle;

    $handle = $actor->username
        ? $actor->username
        : __('profile.username_fallback_prefix').$actor->id;

    $profileUrl = route('profile.show', $actor);

    $verb = match ($event->type) {
        FeedEvent::TYPE_CREATE => __('feed.event.created'),
        FeedEvent::TYPE_VOTE => __('feed.event.voted', ['side' => Str::title((string) $event->sideLabel)]),
        FeedEvent::TYPE_ARGUE => __('feed.event.argued'),
        FeedEvent::TYPE_LIKE => __('feed.event.liked'),
        FeedEvent::TYPE_WIN => __('feed.event.won'),
        FeedEvent::TYPE_LOSE => __('feed.event.lost'),
        default => '',
    };

    $headlineColor = match ($event->type) {
        FeedEvent::TYPE_WIN => 'text-emerald-400',
        FeedEvent::TYPE_LOSE => 'text-red-400',
        default => 'text-white',
    };

    $isResult = in_array($event->type, [FeedEvent::TYPE_WIN, FeedEvent::TYPE_LOSE], true);
    $endedNonResult = ! $isResult && ! $event->isOpen();
@endphp

<article class="rounded-2xl border border-white/5 bg-white/[0.035] p-4">
    <div class="flex items-start gap-3">
        <a href="{{ $profileUrl }}" class="shrink-0">
            @if ($actor->avatarUrl())
                <img src="{{ $actor->avatarUrl() }}"
                     alt="" class="h-9 w-9 rounded-full object-cover">
            @else
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-navy-700 text-sm font-bold text-white/80">
                    {{ mb_strtoupper(mb_substr($actor->name, 0, 1)) }}
                </span>
            @endif
        </a>
        <p class="min-w-0 flex-1 text-sm font-medium {{ $headlineColor }}">
            <a href="{{ $profileUrl }}" class="font-semibold hover:underline">{{ $handle }}</a>
            {{ $verb }}
        </p>
    </div>

    @if ($event->argumentText)
        <blockquote class="mt-2 rounded-xl bg-[#1A1F2B] p-3 text-sm italic text-white/75">
            {{ Str::limit($event->argumentText, 200) }}
        </blockquote>
    @endif

    <div class="mt-3">
        <x-battle-mini-card :battle="$battle" />
    </div>

    <div class="mt-3">
        @if ($isResult)
            <a href="{{ route('battles.show', $battle) }}"
               class="block w-full rounded-xl bg-white/10 px-4 py-2.5 text-center text-sm font-semibold text-white/80 transition hover:bg-white/15">
                {{ __('feed.cta.view_battle') }}
            </a>
        @elseif ($endedNonResult)
            <span class="block w-full cursor-default rounded-xl bg-white/5 px-4 py-2.5 text-center text-sm font-semibold text-white/40">
                {{ __('feed.cta.battle_ended') }}
            </span>
        @else
            <a href="{{ route('battles.show', $battle) }}"
               class="block w-full rounded-xl bg-[#7C3AED] px-4 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-[#6D28D9]">
                @if ($event->type === FeedEvent::TYPE_CREATE)
                    {{ __('feed.cta.vote_with_user', ['user' => $handle]) }}
                @else
                    {{ __('feed.cta.vote_with') }}
                @endif
            </a>
        @endif
    </div>
</article>
```

- [ ] **Step 4: Run the event-card tests to verify they pass**

Run: `make ws` then `vendor/bin/pest tests/Feature/Feed/BattleMiniCardTest.php`
Expected: PASS for the three updated/new event-card tests (banner tests in Task 4 may still need adjustment — run only the event-card cases here with `--filter="event card"` if banner tests interfere).

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/feed/event-card.blade.php tests/Feature/Feed/BattleMiniCardTest.php
git commit -m "feat(feed): sentence-style event card with clickable name, no rank header"
```

---

### Task 4: Battle banner — wide layout, pool under VS, WINNER/LOSER badges

**Files:**
- Modify: `resources/views/components/battle-mini-card.blade.php`
- Test: `tests/Feature/Feed/BattleMiniCardTest.php`

**Interfaces:**
- Consumes: `Battle` (`side_a_label`, `side_b_label`, `status`, `winning_side`, `closes_at`, `isOpenForVoting()`, `compactPool()`, constants `STATUS_SETTLED`, `SIDE_A`, `SIDE_B`), `battle.vs` / `battle.winner_badge` / `battle.loser_badge`.
- Produces: a banner that, for settled battles with a `winning_side`, renders `WINNER`/`LOSER` badges, and always renders `💰 pool` centered under `VS`.

- [ ] **Step 1: Add banner outcome tests (write the failing tests)**

In `tests/Feature/Feed/BattleMiniCardTest.php`, add at the end:

```php
test('battle mini card shows winner and loser badges for a settled battle', function () {
    app()->setLocale('en');

    $battle = Battle::factory()->settled(Battle::SIDE_A)->create([
        'side_a_label' => 'cats',
        'side_b_label' => 'dogs',
    ]);

    $html = Blade::render('<x-battle-mini-card :battle="$battle" />', ['battle' => $battle]);

    expect($html)->toContain('WINNER')
        ->toContain('LOSER');
});

test('battle mini card shows no outcome badges for a tie', function () {
    app()->setLocale('en');

    $battle = Battle::factory()->create([
        'status' => Battle::STATUS_SETTLED,
        'settled_at' => now(),
        'winning_side' => null,
    ]);

    $html = Blade::render('<x-battle-mini-card :battle="$battle" />', ['battle' => $battle]);

    expect($html)->not->toContain('WINNER')
        ->not->toContain('LOSER');
});

test('battle mini card shows no outcome badges while a battle is open', function () {
    app()->setLocale('en');

    $battle = Battle::factory()->create([
        'status' => Battle::STATUS_ACTIVE,
        'opens_at' => now()->subHour(),
        'closes_at' => now()->addHours(5),
    ]);

    $html = Blade::render('<x-battle-mini-card :battle="$battle" />', ['battle' => $battle]);

    expect($html)->not->toContain('WINNER')
        ->not->toContain('LOSER');
});
```

The existing banner tests (`shows titled labels and compact pool`, `shows a timer for an open battle`, `hides the timer for a settled battle`) stay unchanged and must keep passing.

- [ ] **Step 2: Run the banner tests to verify the new ones fail**

Run: `make ws` then `vendor/bin/pest tests/Feature/Feed/BattleMiniCardTest.php --filter="battle mini card"`
Expected: the three new badge tests FAIL (no `WINNER`/`LOSER` markup yet); the existing three still pass.

- [ ] **Step 3: Rewrite the battle-mini-card component**

Replace the entire contents of `resources/views/components/battle-mini-card.blade.php` with:

```blade
@props(['battle'])

@php
    use App\Models\Battle;
    use Illuminate\Support\Str;

    /** @var \App\Models\Battle $battle */
    $open = $battle->isOpenForVoting();
    $timeLeft = ($open && $battle->closes_at) ? $battle->closes_at->diff(now()) : null;
    $timeLabel = $timeLeft
        ? sprintf('%02d:%02d', (int) $timeLeft->format('%a') * 24 + (int) $timeLeft->h, $timeLeft->i)
        : null;
    $sideALabel = Str::title($battle->side_a_label);
    $sideBLabel = Str::title($battle->side_b_label);

    $settled = $battle->status === Battle::STATUS_SETTLED && $battle->winning_side !== null;
    $aWon = $settled && $battle->winning_side === Battle::SIDE_A;
    $bWon = $settled && $battle->winning_side === Battle::SIDE_B;
@endphp

<a href="{{ route('battles.show', $battle) }}"
   class="block rounded-2xl bg-[#1A1F2B] p-4 transition hover:bg-[#202636]">
    <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3">
        <div class="min-w-0 text-left">
            <span class="block truncate text-lg font-bold text-white">{{ $sideALabel }}</span>
            @if ($settled)
                <span class="mt-1 block text-xs font-bold uppercase tracking-wide {{ $aWon ? 'text-emerald-400' : 'text-red-400' }}">
                    {{ $aWon ? __('battle.winner_badge') : __('battle.loser_badge') }}
                </span>
            @endif
        </div>

        <div class="flex shrink-0 flex-col items-center">
            <span class="text-base font-extrabold uppercase tracking-wide text-indigo-400">{{ __('battle.vs') }}</span>
            <span class="mt-1 text-[11px] text-white/55">💰 {{ $battle->compactPool() }}</span>
            @if ($timeLabel)
                <span class="mt-0.5 text-[11px] text-white/55">⏱ {{ $timeLabel }}</span>
            @endif
        </div>

        <div class="min-w-0 text-right">
            <span class="block truncate text-lg font-bold text-white">{{ $sideBLabel }}</span>
            @if ($settled)
                <span class="mt-1 block text-xs font-bold uppercase tracking-wide {{ $bWon ? 'text-emerald-400' : 'text-red-400' }}">
                    {{ $bWon ? __('battle.winner_badge') : __('battle.loser_badge') }}
                </span>
            @endif
        </div>
    </div>
</a>
```

- [ ] **Step 4: Run the full BattleMiniCardTest to verify all pass**

Run: `make ws` then `vendor/bin/pest tests/Feature/Feed/BattleMiniCardTest.php`
Expected: PASS — all banner and event-card tests green.

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/battle-mini-card.blade.php tests/Feature/Feed/BattleMiniCardTest.php
git commit -m "feat(feed): wide battle banner with pool under VS and winner/loser badges"
```

---

### Task 5: Feed page layout — remove heading, full header width

**Files:**
- Modify: `resources/views/livewire/feed-page.blade.php`
- Test: `tests/Feature/Feed/FeedPageTest.php`

**Interfaces:**
- Consumes: existing `$chips`, `$events`, `$filter`, `$hasMore`, `wire:click` handlers (unchanged).

- [ ] **Step 1: Update the page test assertion (write the failing test)**

In `tests/Feature/Feed/FeedPageTest.php`, in the test `settled battle result renders a win headline on the page`, change the final assertion from `->assertSee('@morpheus')` to:

```php
        ->assertSee('morpheus')
        ->assertDontSee('@morpheus');
```

- [ ] **Step 2: Run the page test to verify it fails**

Run: `make ws` then `vendor/bin/pest tests/Feature/Feed/FeedPageTest.php --filter="settled battle result"`
Expected: FAIL — page still renders `@morpheus` via the old card (this should already pass once Task 3 is merged; if Task 3 is done, this step confirms; the explicit `assertDontSee('@morpheus')` is the new guard).

- [ ] **Step 3: Update the page layout**

In `resources/views/livewire/feed-page.blade.php`:

(a) Replace the outer container line:

```blade
<div class="mx-auto max-w-lg px-4 py-6">
```

with:

```blade
<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
```

(b) **Delete** the heading line:

```blade
    <h1 class="text-lg font-semibold text-white">{{ __('nav.feed') }}</h1>
```

(c) Since the heading is gone, change the filter-chip row's top margin from `mt-4` to `mt-0` (it no longer follows a heading). Replace:

```blade
    <div class="-mx-4 mt-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
```

with:

```blade
    <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
```

Leave the `$events` list, empty state, and load-more button unchanged.

- [ ] **Step 4: Run the page test suite to verify it passes**

Run: `make ws` then `vendor/bin/pest tests/Feature/Feed/FeedPageTest.php`
Expected: PASS — all four page tests green.

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/feed-page.blade.php tests/Feature/Feed/FeedPageTest.php
git commit -m "feat(feed): full-width feed page, drop the Feed heading"
```

---

### Task 6: Final verification gate

**Files:** none (verification only).

- [ ] **Step 1: Style fix**

Run: `make pint`
Expected: no diffs reported, or auto-fixes applied (review and re-commit if it touches files).

- [ ] **Step 2: Static analysis**

Run: `make stan`
Expected: PASS at level 6 (no new errors beyond the baseline). If FeedEvent/FeedService changes trip Larastan, address inline.

- [ ] **Step 3: Full test suite**

Run: `make test`
Expected: PASS — entire Pest suite green.

- [ ] **Step 4: Manual visual check (optional but recommended)**

With the stack up (`make up`), visit http://versus.local/feed and confirm: no "Feed" heading, list spans the header width, each card reads "name + action" with a clickable name (no `@`), the banner shows the pool under VS, and a settled battle shows WINNER/LOSER badges.

- [ ] **Step 5: Commit any pint/stan fixups**

```bash
git add -A
git commit -m "chore(feed): pint/stan fixups for feed redesign"
```

---

## Notes for the implementer

- The `before` cursor + `limit` pagination contract is preserved; the only removed machinery is the grouping pass and its over-fetch buffer (grouping no longer happens, so the buffer is moot). `resultEvents()` keeps its own internal `limit * 5` over-fetch — leave it intact.
- Likes surface only in the **All** filter by design (no new chip). Do not add a `FILTER_LIKES`.
- The username link inherits the headline color (green for win, red for lose, white otherwise) — that is intentional and matches the target screenshot.
- `nav.feed` is still used by the nav bar; only the in-page `<h1>` is removed. Do not delete the `nav.feed` key.

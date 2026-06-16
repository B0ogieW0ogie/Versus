# Feed Page — Design

**Date:** 2026-06-16
**Route:** `GET /feed` (`auth`, `verified`) → `App\Livewire\FeedPage` (already wired; currently a placeholder).

## Purpose

Turn the `/feed` placeholder into a real activity feed: a chronological stream of what the
people you follow are doing on the platform — creating battles, voting, arguing, winning and
losing. The feed makes the social graph (follow system) feel alive and pulls users back into
battles via inline `VOTE WITH` calls-to-action.

## Scope decisions (locked)

- **Data:** real data from the DB (no mock array).
- **Feed audience:** activity from users the viewer **follows**, with a **global fallback** —
  if the viewer follows nobody (or the following-feed is empty), show recent global activity so
  the page is never empty.
- **Assembly strategy:** **PHP-merge in a `FeedService`** (approach C). Fetch the top slice from
  each source, merge + sort by timestamp in PHP, group, slice to the page. No new table, no
  changes to money-path Actions, DB-portable across Postgres (dev) and SQLite (tests). The
  service is a swappable seam — a future `feed_events` table can replace its internals without
  touching the Livewire component or Blade.
- **Mini battle card:** a new shared `x-battle-mini-card` Blade component (the existing
  `x-battle-tile` has a different layout/style).
- **Rank** next to `@nickname`: a **placeholder** for now (no real rank concept exists yet).
- **Pagination:** cursor "Load more" button (timestamp cursor, loads older events).

## Event sources → card types

| Card | Source | Actor | Timestamp |
|------|--------|-------|-----------|
| CREATE | `Battle` created by a followed user (`created_by_id`) | `battle.creator` | `battle.created_at` |
| VOTE | `Vote` cast by a followed user | `vote.user` | `vote.created_at` |
| ARGUE | `Comment` posted by a followed user (comments **are** arguments) | `comment.user` | `comment.created_at` |
| WIN / LOSE | settled `Battle` (`status = settled`, `winning_side` set) crossed with that user's vote — WIN if they voted on `winning_side`, else LOSE | the voter | `battle.settled_at` |

Notes:
- WIN/LOSE: a user may have many votes in one battle. Collapse to a **single** result per
  `(user, battle)`: WIN if any of their votes is on the winning side, otherwise LOSE.
- Settled battles with `winning_side = null` (ties / refunds) produce **no** WIN/LOSE event.

## Grouping

- VOTE + ARGUE by the **same user in the same battle** collapse into **one** card.
  - `groupId = "{user_id}:{battle_id}"`.
  - Header text: "@nickname votes and argues" (i18n key).
  - The card shows the argument quote (from the ARGUE part) and **one** shared `VOTE WITH` button.
  - Card timestamp = the most recent of the two.
- CREATE and WIN/LOSE events are **always standalone** (never merged).
- Grouping applies only in the **All** view. Type-filtered views (Votes / Arguments) show events
  individually.

## Filters

Horizontal-scroll chip row. Active chip filled `#7C3AED`, inactive muted.

| Chip | Shows |
|------|-------|
| All | every event type (with grouping) |
| Votes | VOTE events only |
| Arguments | ARGUE events only |
| Created | CREATE events only |
| Results | WIN + LOSE events |

## Card anatomy (per spec)

Every card: avatar + `@nickname` (rank placeholder) header, a type-specific line, an embedded
`x-battle-mini-card`, and a CTA.

- **CREATE** — "@nickname created a battle". CTA `VOTE WITH @nickname` if the battle is open;
  `BATTLE ENDED` (disabled/secondary) if closed/settled.
- **VOTE** — "@nickname votes in a battle". CTA `VOTE WITH`.
- **ARGUE** — like VOTE + the argument quote in an italic block on a darker panel (`#1A1F2B`).
- **WIN / LOSE** — "@nickname won" (green) / "@nickname lost" (red). CTA `VIEW BATTLE` (grey).
- **Grouped (vote+argue)** — "@nickname votes and argues" + argument quote + one `VOTE WITH`.

`VOTE WITH` / `VIEW BATTLE` / `BATTLE ENDED` link to the battle page (`route('battles.show', $battle)`).
(`VOTE WITH` can deep-link to the battle's vote widget; final anchor TBD during implementation —
defaults to the battle page.)

## Components

- **`App\Services\Feed\FeedEvent`** — readonly DTO. Fields: `type` (create|vote|argue|win|lose|
  vote_argue), `actor: User`, `battle: Battle`, `occurredAt: CarbonInterface`,
  `argumentText: ?string`, `result: ?string` (won|lost). Helpers: `isOpen()`, `headlineKey()`.
- **`App\Services\Feed\FeedService`** — public `events(User $viewer, string $filter, ?CarbonInterface $before, int $limit): Collection<FeedEvent>`.
  - Resolves followed-user id set; falls back to "global" (all users, or at least everyone but
    the viewer) when empty.
  - Queries each enabled source (eager-loading `creator`/`user` + `battle` + `battle.category`),
    each capped at ~`limit + buffer` and `created_at < before` when a cursor is given.
  - Merges, sorts desc by `occurredAt`, groups, returns the first `limit`.
- **`App\Livewire\FeedPage`** — public `string $filter = 'all'`, cursor state, `setFilter($f)`,
  `loadMore()`. Renders accumulated events; `loadMore` extends the list. Thin: delegates all
  synthesis to `FeedService`.
- **`resources/views/components/battle-mini-card.blade.php`** (`x-battle-mini-card`) — A VS B,
  pool, timer (if `isOpenForVoting()`); bg `#1A1F2B`, `rounded-2xl` (16px). Reusable.
- **`resources/views/components/feed/event-card.blade.php`** (+ small sub-partials as needed) —
  renders a `FeedEvent`: header, type line, mini-card, CTA.
- **View:** `resources/views/livewire/feed-page.blade.php` — header, filter chips, event list,
  Load-more button, empty state.

## i18n

All user-facing strings added to **both** `lang/en/feed.php` and `lang/ru/feed.php`. Keys (draft):
`filters.all`, `filters.votes`, `filters.arguments`, `filters.created`, `filters.results`,
`event.created`, `event.voted`, `event.argued`, `event.vote_and_argue`, `event.won`, `event.lost`,
`cta.vote_with`, `cta.vote_with_user`, `cta.view_battle`, `cta.battle_ended`, `load_more`,
`empty.title`, `empty.body`. (Existing `feed.placeholder` may be removed once the page is real.)

## Testing

Pest feature/unit tests (opt into DB via `RefreshDatabase`; SQLite-compatible — no Postgres-only
SQL):

- `FeedService` emits a CREATE/VOTE/ARGUE/WIN/LOSE event for each corresponding action by a
  followed user.
- Activity by a **non-followed** user is excluded (when the viewer follows someone).
- Global fallback kicks in when the viewer follows nobody.
- WIN vs LOSE resolves correctly from `winning_side`; tie (`winning_side = null`) yields no result event.
- Grouping: same user voting **and** arguing in the same battle yields **one** `vote_argue` event;
  different battles stay separate; CREATE/WIN never merge.
- Filter narrows to the right type(s); pagination cursor returns older events without duplicates.

## Out of scope (deferred)

- A denormalized `feed_events` table / writing events from the Actions (approach A) — revisit if
  the PHP-merge query cost becomes a problem.
- Real user-rank concept (placeholder only for now).
- Real-time/live updates (polling, websockets).
- Reactions/likes on feed events.

## Subtask breakdown

1. `x-battle-mini-card` component — build + verify in isolation.
2. `FeedEvent` DTO + `FeedService` synthesis for the 4 sources (no grouping) + feature tests.
3. Grouping logic (vote+argue merge) + tests.
4. Event-card Blade partials — the 5 card types with correct text, colors, CTAs.
5. `FeedPage` rewrite — filters, follow/global-fallback wiring, `loadMore` cursor pagination.
6. Header + filter chips styling.
7. i18n strings (en + ru); run `make pint && make stan && make test`.

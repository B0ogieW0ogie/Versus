# Feed Page Redesign — Design

Date: 2026-06-23
Branch: `feature/feed-redesign`

## Goal

Rework the activity feed page (`/feed`) so each item reads as a short sentence
about a user action, spans the full header width, and shows a redesigned battle
banner that surfaces the live pool and (for finished battles) the WINNER/LOSER
outcome.

Reference: two screenshots provided by the user — screen 1 is the current
state, screen 2 is the target look (wide banner, large side names, colored
LOSER/WINNER labels, pool under VS).

## Affected files

- `resources/views/livewire/feed-page.blade.php` — page layout
- `resources/views/components/feed/event-card.blade.php` — per-event card
- `resources/views/components/battle-mini-card.blade.php` — battle banner
- `app/Services/Feed/FeedService.php` — event assembly + queries
- `app/Services/Feed/FeedEvent.php` — value object (new type, side label)
- `lang/en/feed.php`, `lang/ru/feed.php` — strings
- `lang/en/battle.php`, `lang/ru/battle.php` — WINNER/LOSER labels (if not already present)
- `tests/` — FeedService coverage

## 1. Page layout (`feed-page.blade.php`)

- Remove the `<h1>{{ __('nav.feed') }}</h1>` heading.
- Container width: `max-w-lg` → `max-w-7xl mx-auto px-4 sm:px-6 lg:px-8`,
  matching the header/nav bar (`layouts/app.blade.php` and
  `layouts/navigation.blade.php` both use `max-w-7xl mx-auto px-4 sm:px-6 lg:px-8`).
- Filter chips, `setFilter`, `loadMore`, pagination, and empty state are unchanged.

## 2. Event card (`event-card.blade.php`)

- **Remove** the separate header block (avatar + `@username` + rank
  "Rookie"). The duplicated handle and the `rank_placeholder` go away.
- Keep: avatar on the left + a **sentence row** that begins with the
  clickable **username without `@`** (link to `route('profile.show', $actor)`).
- Headline color: win = green (`text-emerald-400`), lose = red
  (`text-red-400`), all other events = white. The username is always a
  clickable link (bold, hover underline) and inherits the row color.
- The username link renders separately from the translated verb phrase. Word
  order is subject-first in both EN and RU, so the rendered row is:
  `<a>{username}</a> {verb phrase}`.
- The argument `blockquote` is kept for comment and like events
  (`$event->argumentText`).
- The CTA button ("VIEW BATTLE" / "VOTE WITH") is kept.

## 3. Event text (5 types)

Translation strings hold the phrase **without** the user's name (the name is
rendered as a separate link). Subject-first order works for EN and RU.

| Type      | EN phrase                  | RU phrase                  |
|-----------|----------------------------|----------------------------|
| win       | won the battle             | выиграл баттл              |
| lose      | lost the battle            | проиграл баттл             |
| argue     | commented on the battle    | прокомментировал баттл     |
| like      | liked an argument          | лайкнул аргумент           |
| vote      | voted for :side in the battle | проголосовал за :side в баттле |
| create    | created a battle           | создал баттл               |

`:side` is the chosen side's label (`side_a_label` / `side_b_label`).

## 4. FeedService / FeedEvent

- **Remove grouping**: drop `TYPE_VOTE_ARGUE` and the vote+argue collapse logic.
  A vote and a comment on the same battle are now two separate cards.
- **Add `TYPE_LIKE`** + a `likeEvents()` query:
  - Source: `comment_likes` joined to `comments` → `battles`.
  - Actor = the like's `user`.
  - `argumentText` = the liked comment's body.
  - `battle` = the comment's battle.
  - `occurredAt` = the like's `created_at`.
  - Likes appear in the **All** filter only (no new filter chip).
- **Vote events** carry the chosen `side` and its label on `FeedEvent` so the
  `voted for :side` string can be filled in.
- Merge + sort by `occurredAt` desc across all event types as today.

## 5. Battle banner (`battle-mini-card.blade.php`) — screen 2 look

Wide bar, `grid-cols-[1fr_auto_1fr]`, large side names at the edges
(`text-xl`/`text-2xl`), `VS` centered, pool under `VS`.

- **Active battle** (`isOpenForVoting()` / not settled):
  `[side A name] — VS — [side B name]`, `💰 {{ compactPool() }}` under VS,
  optional `⏱ {timeLabel}` (kept from current behavior).
- **Finished battle** (`status === STATUS_SETTLED`):
  - colored labels between each name and VS — losing side `LOSER` (red),
    winning side `WINNER` (green), based on `winning_side`.
  - tie / refund (`winning_side === null`): no LOSER/WINNER labels.
  - pool under VS.

The banner remains a link to `route('battles.show', $battle)`.

## 6. Translations

- `lang/en/feed.php`, `lang/ru/feed.php`:
  - update `event.*` to the phrases above (without `:user`),
  - add `event.liked`,
  - change `event.voted` to include `:side`,
  - remove `event.vote_and_argue` and `rank_placeholder`.
- `lang/en/battle.php`, `lang/ru/battle.php`: add `winner` / `loser` labels if
  not already present.

## 7. Testing

Pest. Add/update `FeedService` coverage:
- like events appear in the All feed with the liked comment text,
- vote and argue on the same battle produce two separate cards (no grouping),
- vote event exposes the chosen side label,
- finished battle banner data exposes winner/loser correctly (tie → neither).

## Out of scope

- No new filter chip for likes.
- No anti-whale weight changes; banner pool uses `total_pool` / `compactPool()`.
- Profile page username display is unchanged.

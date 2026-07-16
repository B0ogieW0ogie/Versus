# Design: Notification bell — in-app notifications + sound

**Date:** 2026-07-16
**Status:** Approved (design), pending spec review

## Problem

The bell icon in the authenticated header
(`resources/views/layouts/navigation.blade.php`) is a "coming soon" placeholder.
There is no notification infrastructure at all: no `notifications` table, no
websockets, nothing user-facing. Users never learn that a battle they staked on
was settled, that a referral payout landed, or that someone replied to / liked
their comment — unless they go looking.

## Decisions (from brainstorming)

- **Events:** battle settled (won / lost / refunded, with amount), referral
  payout, reply to my comment, like on my comment.
- **Delivery:** rare polling — `wire:poll.visible.60s` on the bell component
  (counter-only refresh). No websockets, no background polling when the tab is
  hidden. Up-to-a-minute latency is acceptable.
- **Sound:** one short "ding" when the unread count *increases* (even if several
  notifications arrived in one poll). Sound toggle in the dropdown header,
  persisted in `localStorage`, **on by default**. Browser autoplay policy means
  no sound before the first user interaction with the page — degrade silently
  (`.play().catch(() => {})`).
- **Read semantics:** opening the dropdown marks everything read and zeroes the
  badge (Telegram-style). Clicking a notification navigates to the related
  battle page.
- **Storage:** Laravel's native database notifications (Approach A). `User`
  already imports `Notifiable`; we use the framework's `notifications` table,
  `unreadNotifications`, `markAsRead()`. No custom model.

## Storage & generation

Migration: the stock `notifications` table (uuid PK, morph to notifiable,
`type`, JSON `data`, `read_at`). SQLite-compatible — tests run on `:memory:`.

Four notification classes in `app/Notifications/`, all `via(): ['database']`,
each storing a self-contained `data` payload (snapshot at send time, so later
edits/deletes can't break rendering):

| Class | `data` payload |
|---|---|
| `BattleSettled` | `battle_id`, `battle_slug`, `battle_title`, `result` (`won`/`lost`/`refunded`), `amount` (payout or refunded stake; `0` for lost) |
| `ReferralPayout` | `battle_id`, `battle_slug`, `battle_title`, `referee_name`, `amount` |
| `CommentReplied` | `battle_id`, `battle_slug`, `battle_title`, `comment_id`, `actor_name` |
| `CommentLiked` | `battle_id`, `battle_slug`, `battle_title`, `comment_id`, `actor_name` |

### Send points

All sends happen **after** the enclosing `DB::transaction` commits — never
inside it (don't hold row locks while writing notification rows; don't notify
about work that may roll back).

- **`SettleBattleAction`** — while settling (inside the transaction) collect a
  plain array of `{user_id, result, amount}` per voter (one entry per user even
  if they cast several votes: sum the amounts) plus `{referrer_id, referee_name,
  amount}` for each referral payout actually paid (> 0). After the transaction
  returns, send `BattleSettled` / `ReferralPayout` from that array. Tie →
  everyone gets `refunded` with their total stake back.
- **`PostCommentAction`** — if the new comment has a parent: notify the unique
  set of {parent comment author, `reply_to_user`} minus the actor with
  `CommentReplied`. Top-level comments notify nobody.
- **`LikeCommentAction`** — on a *new* like only (the existing `already_liked`
  path returns early): notify the comment author with `CommentLiked`, unless
  the liker is the author.

Never notify the actor about their own action (self-reply, self-like,
self-referral — the latter already pays nothing so no entry is collected).

## UI — `NotificationBell` Livewire component

Replaces the placeholder `<button>` in `navigation.blade.php` (rendered only
for authenticated users; the guest header keeps no bell).

- **Badge:** unread count, capped display at `99+`. Hidden when zero.
- **Polling:** `wire:poll.visible.60s` re-renders the counter only (the
  component keeps the dropdown list lazy — it is queried when the dropdown
  opens, not on every poll).
- **Dropdown** (Alpine `x-data` open/close, closes on outside click / Escape):
  - Header row: title + sound on/off toggle (speaker icon).
  - Last 15 notifications, newest first: icon per type, one-line text, relative
    time (`diffForHumans`), whole row is a link to
    `route('battles.show', slug)` (comment notifications append
    `#comment-{id}`).
  - Opening the dropdown calls `markAllRead()` → `unreadNotifications->markAsRead()`
    and zeroes the badge. Unread rows are visually distinct until the next
    render (subtle background tint).
  - Empty state: bell-slash icon + "no notifications yet" line.
- **Texts:** new `lang/en/notifications.php` + `lang/ru/notifications.php`
  (title, empty state, per-type message templates with `:amount`, `:name`,
  `:battle` placeholders, sound toggle labels).

## Sound

- Asset: a short (~0.3 s) ding at `public/sounds/notification.mp3`, generated
  once at implementation time (e.g. via ffmpeg sine synthesis), a few KB.
- Alpine logic on the component root:
  - Track the previous unread count in the Alpine scope. After each Livewire
    morph (poll), if `newCount > prevCount` and sound is enabled → `new
    Audio(...).play().catch(() => {})`. Initial page load sets the baseline
    without playing.
  - `soundEnabled` persisted as `localStorage['versus_notification_sound']`
    (default enabled). Toggle button flips it; pure client-side, no server
    round-trip.

## Error handling

- Notification sending is best-effort decoration on top of money flows: wrap
  the post-commit send loop in `try/catch` + `report()` so a notification
  failure can never mark a settled battle as failed (settlement has already
  committed by then).
- Deleted battle/comment behind a notification: payload is self-contained, the
  link may 404 — acceptable for MVP, no cleanup job.

## Testing (Pest, `RefreshDatabase`)

- `SettleBattleAction`: winners get `BattleSettled` `won` with their exact
  payout (incl. rounding-residue absorber), losers get `lost`/`0`, tie →
  `refunded` with stake; multi-vote user gets exactly **one** notification with
  the summed amount; referrer gets `ReferralPayout` only when the payout > 0.
- `PostCommentAction`: reply notifies parent author; self-reply notifies
  nobody; `reply_to_user` distinct from parent author gets notified too;
  top-level comment notifies nobody.
- `LikeCommentAction`: first like notifies the author; repeat like and
  self-like do not.
- `NotificationBell` (Livewire test): badge shows unread count; opening the
  dropdown marks all read and zeroes the badge; renders per-type text; guest
  page renders no component.

Use real database notifications in action tests (assert rows / `Notification::fake()`
where cleaner). Sound is untested client-side glue.

## Out of scope

- Websockets / real-time push, browser (Web Push) notifications.
- Notification preferences per event type, email digests.
- A dedicated "all notifications" page (dropdown-only for MVP).
- Cleanup/pruning of old notification rows.

# Design: "Agreed with your argument" notification

**Date:** 2026-07-16
**Status:** Approved (design), pending spec review
**Follows:** [2026-07-16-notification-bell-design.md](2026-07-16-notification-bell-design.md)

## Problem

Supporting an argument is the strongest signal a comment can get — the
supporter stakes real tokens on the side that comment argues for
([CommentThread::supportComment()](../../../app/Livewire/CommentThread.php)
→ `CastVoteAction`). The comment's author is never told. Replies and likes both
notify; the more meaningful action does not.

## Decisions (from brainstorming)

- **Generic text, no amounts.** "X agreed with your argument in :battle" — the
  stake size stays out of the notification.
- **One notification per supporter per comment.** Repeat supports by the same
  person are silent. Without amounts every repeat would render identical text,
  so three in a row would read as a bug rather than as activity.
- **Never notify the author about their own support.**
- **Domain rule lives in an action,** not in the Livewire component — beside
  the three existing notification rules.

## Notification class — `app/Notifications/ArgumentSupported.php`

`new ArgumentSupported(Battle $battle, Comment $comment, User $actor)`, database
channel only, same shape as `CommentReplied`/`CommentLiked` plus `actor_id`:

| key | value |
|---|---|
| `battle_id`, `battle_slug`, `battle_title` | battle snapshot |
| `comment_id` | the supported comment |
| `actor_name` | supporter's name at send time (rendered) |
| `actor_id` | supporter's id (dedupe key only, never rendered) |

## Action — `app/Actions/Comments/SupportCommentAction.php`

```php
__invoke(User $user, Battle $battle, Comment $comment, float $amount): Vote
```

Calls `CastVoteAction` with `$comment->side` and returns its `Vote`.
`CastVoteAction` wraps itself in `DB::transaction`, so the notification is sent
after it returns — i.e. after commit — wrapped in `try/catch (\Throwable)` +
`report()`, matching every other send point on this feature.

Notification is skipped when:

- the supporter is the comment's author (`$comment->user_id === $user->id`), or
- this supporter already has an `ArgumentSupported` recorded for this comment.

### Dedupe — why the notifications table is the source of truth

`votes` has no `comment_id`: a stake does not record which argument it was cast
through. So "has Bob supported comment #5 before?" is unanswerable from the
money tables. Adding a column to `votes` for the sake of a notification rule is
not worth touching the ledger schema, so the dedupe key is the notification
history itself:

```php
$comment->user->notifications()
    ->where('type', ArgumentSupported::class)
    ->where('data->comment_id', $comment->id)
    ->where('data->actor_id', $user->id)
    ->exists()
```

Scoped to the author's `notifiable_id` (indexed by the stock morph index), so
this is a small scan over one user's rows. Accepted consequence: if notification
pruning is ever added, a repeat support after a prune notifies again — the
desired behaviour anyway.

### The `data` column must be `json`, not `text`

Laravel's stock notifications table declares `data` as **text** — the framework
never queries into it. This query does, and Postgres has no `->>` operator for a
text column, so it raises `SQLSTATE[42883]` and, because the send is
best-effort, the notification silently never arrives. Migration
`2026_07_16_120000_change_notifications_data_to_json` alters the column
(`USING data::json`), guarded to `pgsql` — SQLite applies `json_extract` to text
happily and needs no change.

The create-table migration is **not** edited in place: it already shipped to
origin/main and ran on production, so an in-place edit would never execute there.

**This class of bug is invisible to the test suite.** Tests run on SQLite, where
the JSON query works against a text column; the failure only exists on Postgres.
The bug was caught by exercising the feature in a browser against the dev
database, and that is the only gate that can catch the next one.

## Livewire — `app/Livewire/CommentThread.php`

`supportComment()` calls `SupportCommentAction` instead of `CastVoteAction`,
passing `$comment` rather than `$comment->side`. `ValidationException` handling,
`afterStakeSuccess()`, and the login redirect are unchanged; the component stays
thin.

## Rendering — `app/Livewire/NotificationBell.php`

One new arm in `message()`:

```php
'ArgumentSupported' => __('notifications.argument_supported', [
    'name' => (string) $data['actor_name'],
    'battle' => $battle,
]),
```

`url()` needs no change — it already appends `#comment-{id}` whenever the
payload carries `comment_id`.

## i18n

`notifications.argument_supported`, added to both locales:

- en: `:name agreed with your argument in ":battle".`
- ru: `:name согласился(ась) с вашим аргументом в баттле «:battle».`

## Testing

Pest, `use RefreshDatabase;`:

- First support notifies the comment author with the actor's name and the
  comment id.
- A second support of the same comment by the same user sends nothing.
- A different user supporting the same comment does notify.
- Self-support sends nothing.
- The action still casts the vote (balance debited, `Vote` row written) — the
  notification wrapper must not disturb the money path.
- `NotificationBell` renders the new type's text.

Browser check on a live battle: support an argument as another user, confirm the
author's bell shows the message and the row links to `#comment-{id}`.

## Out of scope

- Notifying on stake *size* thresholds, aggregating "3 people agreed".
- A `comment_id` column on `votes` (the honest-data alternative to the dedupe
  query above) — revisit only if support-per-comment analytics are wanted.

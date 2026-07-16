# Design: LAST SHOT notification

**Date:** 2026-07-16
**Status:** Approved (design), pending spec review
**Follows:** [2026-07-16-notification-bell-design.md](2026-07-16-notification-bell-design.md)

## Problem

When a battle closes with the sides exactly tied, it enters
`Battle::STATUS_LAST_SHOT` instead of settling
([SettleBattleAction](../../../app/Actions/Battles/SettleBattleAction.php)), and
`isOpenForVoting()` reopens staking past the deadline. The next stake breaks the
tie and settles the battle immediately (`CastVoteAction` calls
`SettleBattleAction` when `$wasLastShot`).

Nobody is told. The one moment where a single stake decides everything — and the
people with money in the pool find out only if they happen to reload the page.

## Decisions (from brainstorming)

- **Recipients: everyone who staked**, both sides, one notification per person
  regardless of how many votes they cast. Commenters who never staked are not
  notified — for them it would be advertising, not news.
- **No amounts, no side** in the text: the message is identical for everyone and
  reads as a call to come back.
- **No dedupe query needed** (see idempotency below).

## Notification class — `app/Notifications/BattleLastShot.php`

`new BattleLastShot(Battle $battle)`, database channel only:

| key | value |
|---|---|
| `battle_id`, `battle_slug`, `battle_title` | battle snapshot |

No `comment_id`, so `NotificationBell::url()` links to the battle page without an
anchor — existing behaviour, no change needed there.

## Sending — `app/Actions/Battles/SettleBattleAction.php`

The LAST SHOT branch (where `$weightA === $weightB && $weightA > 0.0`) collects
the distinct staker ids inside the transaction:

```php
$this->lastShotVoterIds = Vote::where('battle_id', $battle->id)
    ->distinct()
    ->pluck('user_id')
    ->all();
```

`sendNotifications()` currently returns early unless the battle ended
`STATUS_SETTLED` — that guard is what keeps a tie silent today. It gains a second
branch:

- `STATUS_SETTLED` → today's `BattleSettled` / `ReferralPayout` sends.
- `STATUS_LAST_SHOT` → `BattleLastShot` to each collected staker.

Sending stays after the `DB::transaction` commits, wrapped in
`try/catch (\Throwable)` + `report()`, like every other send point on this
feature.

### Idempotency — why no dedupe query

LAST SHOT is entered exactly once per battle, verified along both call paths:

- The cron ([SettleDueBattlesCommand](../../../app/Console/Commands/SettleDueBattlesCommand.php))
  selects `STATUS_ACTIVE` only, so it never revisits a battle already sitting in
  `STATUS_LAST_SHOT`.
- The other caller is `CastVoteAction` when `$wasLastShot`. That vote lands on
  one side with `amount > 0`, so equal weights become unequal — the tie always
  breaks and the battle settles. It cannot re-enter LAST SHOT.

So, unlike the support-argument notification, there is nothing to dedupe against.

## i18n

`notifications.battle_last_shot`, in both locales, echoing the existing
`battle.last_shot_hint` ("Следующая ставка решает исход"):

- en: `LAST SHOT in ":battle" — the stakes are tied and the next bet decides it.`
- ru: `LAST SHOT в баттле «:battle» — ставки равны, следующая ставка решает исход.`

## Rendering — `app/Livewire/NotificationBell.php`

One new arm in `message()`:

```php
'BattleLastShot' => __('notifications.battle_last_shot', ['battle' => $battle]),
```

## Testing

Pest, `use RefreshDatabase;`:

- A tie notifies every staker exactly once, both sides.
- A user with several votes gets one notification, not one per vote.
- A non-staker (e.g. a commenter) gets nothing.
- A normal (non-tied) settlement sends no `BattleLastShot` at all.
- The tie-break vote settles the battle and sends `BattleSettled` — not a second
  `BattleLastShot`.

Browser check on Postgres is mandatory, not optional: the previous notification
shipped with a Postgres-only defect that the SQLite suite could not see.

## Out of scope

- Notifying commenters or followers.
- A countdown/urgency mechanic beyond the existing LAST SHOT badge.
- Re-notifying if a LAST SHOT battle sits untouched (no re-entry path exists).

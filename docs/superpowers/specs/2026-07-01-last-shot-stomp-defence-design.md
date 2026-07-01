# LAST SHOT + Stomp Defence — Design

**Date:** 2026-07-01
**Status:** Approved

Two new settlement-time battle mechanics for the Versus opinion market.

## Summary

1. **LAST SHOT** — when a battle's timer ends with sides at exactly 50-50, the battle does
   not settle. It enters a `last_shot` state (shown in HOT with a flashing "LAST SHOT" label),
   stays open for voting indefinitely, and the next single vote on either side gives that side
   a guaranteed win and settles the battle immediately.

2. **Stomp Defence** — when, at settlement, one side holds ≥ 90% of total weight, the battle
   is declared void ("несостоявшийся"): every stake is refunded 100% with no fees taken.

## Decisions (from brainstorming)

- **LAST SHOT lifecycle:** battle hangs indefinitely until the next vote; that first vote
  settles it immediately (no overtime window).
- **Stomp refund:** full 100% refund, **no** project/burn/reward fees.
- **Stomp trigger:** share ≥ 90% by weight only — no minimum-participation requirement (a
  single 100% vote is therefore also void).
- **Precedence:** `Empty → Stomp → 50-50 → winner`. A LAST SHOT tie-break vote wins
  immediately and does **not** re-run the Stomp check.
- **"50-50" definition:** exact equality of non-zero weights (`weightA === weightB && weightA > 0`).
  Money is stored at 2 decimals, so exact comparison is correct; no tolerance band.
- **Detection timing:** both mechanics are evaluated only at settlement (timer end), not live
  during the battle.

## 1. Battle state model

- New status constant `Battle::STATUS_LAST_SHOT = 'last_shot'`.
- New nullable column `battles.void_reason` (string) with model constants
  `Battle::VOID_EMPTY = 'empty'`, `Battle::VOID_STOMP = 'stomp'` to distinguish why a settled
  battle was void, for history/UI.
- `Battle::isOpenForVoting()` returns `true` also when `status === STATUS_LAST_SHOT`
  (ignoring the elapsed `closes_at`), so the tie-break vote is accepted.

Migration: `status` is already a plain string column, so no enum change is needed. Add
`void_reason` as a nullable string.

## 2. Settlement — new ordering (`SettleBattleAction`)

Restructure the head of `__invoke` to the approved precedence:

1. **Empty** (`pool <= 0`) → `status = settled`, `void_reason = empty`, `total_pool = 0`.
   (existing early-return path, now also stamps `void_reason`.)
2. **Stomp** — if `max(weightA, weightB) / (weightA + weightB) >= config('versus.mechanics.stomp_threshold')`
   (0.90): full refund via `refundAll()` with **no** system-share credits, then
   `status = settled`, `void_reason = stomp`, `total_pool = pool`.
3. **50-50** (`weightA === weightB && weightA > 0`) → `status = last_shot`; do **not**
   settle, do **not** credit fees, leave `settled_at` null. Return.
4. **Otherwise** — normal payout: credit system shares (project/burn/reward), then distribute
   88% to the winning side proportionally by weight (unchanged logic, including
   last-winner-absorbs-residue and referral payout).

Consequence: the old tie path (tie → refund *after* system shares were already credited) is
removed — 50-50 now always becomes LAST SHOT. This also fixes the current inconsistency where
a tie credited system shares yet refunded 100% of stakes.

`refundAll()` is reused for Stomp; it already refunds each vote's full `amount` and writes a
`vote_payout` transaction. The `meta.reason` for stomp refunds is `'stomp_refund'`.

## 3. Tie-break vote (`CastVoteAction`)

- Voting on a `last_shot` battle passes the normal checks (balance, `max_vote_amount`,
  `max_battle_stake_per_user`) — `isOpenForVoting()` now permits it.
- Capture `$wasLastShot = ($battle->status === Battle::STATUS_LAST_SHOT)` before recording.
  After the vote is written, if `$wasLastShot`, invoke `SettleBattleAction` immediately within
  the same DB transaction. The new stake breaks the weight equality, so the voted side leads
  and `decideWinner` awards it the win. No repeat Stomp check runs (precedence → winner).
- The battle row is already `lockForUpdate`-locked, so a concurrent opposite-side vote in the
  same minute cannot re-tie or flip the outcome — the first accepted vote wins and settles.

## 4. Cron / command

`SettleDueBattlesCommand` is unchanged in logic: it selects only `STATUS_ACTIVE` battles, so a
`last_shot` battle is never re-picked and hangs indefinitely until a vote arrives. (Accepted
tradeoff: an idle LAST SHOT battle lives forever; manual admin close is out of scope here.)

## 5. Config

`config/versus.php`:

```php
'mechanics' => [
    'stomp_threshold' => 0.90, // side share at/above which the battle is void
],
```

No numeric config is needed for LAST SHOT (indefinite).

## 6. Frontend

- **LAST SHOT in HOT:** `BattleIndex` includes `last_shot` battles in the `$hot` section,
  pinned to the top, with a flashing **LAST SHOT** label (Tailwind `animate-pulse` or a custom
  keyframe) on the card and on the battle page.
- The vote widget on a `last_shot` battle page is open; the countdown is replaced by a
  LAST SHOT plate.
- **Stomp:** a settled battle with `void_reason = stomp` shows a "Stomp Defence · stakes
  refunded" plate.
- i18n: add `battle.last_shot`, `battle.stomp_defence`, `battle.void_refunded` to both
  `lang/en/battle.php` and `lang/ru/battle.php`.

## 7. Tests (Pest, TDD)

- Stomp: ≥ 90% → full refund, no system-share transactions written, `void_reason = stomp`.
- Boundary: exactly at threshold vs just below 90% → below is a normal winner.
- 50-50 → battle is `last_shot`, not `settled`, `isOpenForVoting()` true.
- Tie-break vote on `last_shot` → immediate settlement, voted side wins, 88% distributed.
- Update the existing tie-refund test to the new LAST SHOT behavior.

## Out of scope

- Live (pre-timer) detection of either condition.
- Overtime window for LAST SHOT.
- Admin manual close/settle of a hung LAST SHOT battle.
- Anti-whale weight formula (still `weight === amount`, deferred to V2).

# Polymarket-style vote widget

Redesign the battle-page voting UX to match the compact, side-first flow seen on Polymarket (`/event/us-x-iran-permanent-peace-deal-by`): a sticky right-column widget with side pills, prominent amount input, quick-add chips, and a live payout preview culminating in a single CTA.

## Goals

- Replace the inline two-button voting UI inside the central battle card with a focused, Polymarket-like widget.
- Make pool economics tangible by surfacing both pool share (%) and payout multiplier (×) live, instead of the current `N tokens → N votes` rate hint.
- Keep the emotional "A vs B" hero card as context/story; move the transactional part to a dedicated panel.

## Non-goals

- No secondary market. No "Sell" tab, no limit orders, no order book.
- No change to the underlying economic model. `CastVoteAction`, `SettleBattleAction`, `transactions` ledger, per-vote `max_vote_amount` cap, weight=amount — all unchanged.
- No change to comments section or `SidebarWidgets` component beyond layout reordering.

## Layout

Two-column grid on `lg+`, single column below (widget collapses under the hero card on mobile/tablet):

```text
┌──────────────────────────────┬───────────────┐
│  Hero card (A vs B, pool)    │  Vote widget  │  ← sticky (top-6)
│                              │               │
│  Comments                    │               │
└──────────────────────────────┴───────────────┘
```

The existing `resources/views/livewire/battle-show.blade.php` already uses `grid-cols-[1fr_320px]` with `SidebarWidgets` in the aside. The new vote widget lives in the aside **above** `SidebarWidgets`, and the in-hero vote form is removed.

## Widget anatomy (top → bottom)

1. **Header** — small stacked avatars of side A/B (28px, overlapping) + closing date (`Завершается 22 апреля`), parsed from `battle.closes_at`.
2. **Side pills** — two buttons side-by-side, each showing side label + current pool share `%`. Exactly one is active. Default-selected side: the one the user already has the larger stake on; if stakes are equal or zero, side A. Colors: A uses blue gradient, B uses purple gradient, matching the existing `vote-blue-*` / `vote-purple-*` tokens.
3. **Amount block** — label "Сумма", large right-aligned number input (font size ~28px, monospace) with 🪙 suffix. Follows Polymarket's visual emphasis on the amount.
4. **Chips** — `+10`, `+100`, `+1000`, `Макс` as pill buttons. First three add to the current amount (clamped). `Макс` sets amount to `min(balance, max_vote_amount)`.
5. **Payout preview** — visible when `amount ≥ 1`. Format: `Если {SIDE} побеждает → {payout} 🪙 (×{multiplier})`. Computed client-side (see Economic preview below).
6. **CTA** — one full-width button. Label: `Проголосовать за {SIDE} · {amount} 🪙`. Disabled when amount is invalid, user can't vote, or submission is in flight.
7. **Meta** — balance readout and 88/5/3/4 distribution disclaimer (already exists, just repositioned).

## Economic preview

Client-side Alpine component computes payout for display only — actual amounts are computed server-side at settlement. Formula uses current pool state (NOT including the vote being previewed):

```text
poolChosen  = current pool on chosen side (tokens)
poolOther   = current pool on other side
winnersCut  = config('versus.distribution.winners')  // 0.88
multiplier  = (winnersCut * (poolChosen + poolOther)) / poolChosen
payout      = amount * multiplier                     // rounded to whole tokens for display
```

Edge cases:

- `poolChosen == 0` → show `×—` and hide numeric payout (early phase of a battle).
- `poolOther == 0` → user is staking alone on their side; multiplier falls to `winnersCut`, meaning they get back less than they put in even if they win — show the number as-is, it's honest.
- Pool share `%` on the side pill is `pool_side / (poolA + poolB)`, rounded to integer; if `totalPool == 0`, show `50%` for both.

The preview is a *pre-vote snapshot*. We do NOT include the user's own about-to-be-placed stake in the multiplier. Polymarket prices behave the same way — the number you see is the market before your trade moves it. This is simpler and avoids surprising the user if the displayed payout were lower than their static read.

## Component split

- **`App\Livewire\BattleVoteWidget`** (new) — owns the widget: holds `battle`, exposes `castVote(side, amount)`, emits `battle-voted` and `balance-updated` events as the current inline form does. Renders `livewire/battle-vote-widget.blade.php`.
- **`App\Livewire\BattleShow`** — loses its `voteFor` method; hero card drops the `@auth ... @endauth` voting block and just displays the A/B hero, pool total, timer. `supportFor` (the comment-support shortcut) stays where it is.
- **`livewire/battle-vote-widget.blade.php`** (new) — markup + Alpine `x-data` for amount, chosen side, pool mirrors, derived payout/multiplier/percent.

Splitting is worth it because the widget owns non-trivial state (chosen side, amount, chips, payout) and will be re-rendered on every vote. Keeping it separate from `BattleShow` also keeps `BattleShow` focused on battle-level concerns (comments, display) and stops the Blade file growing further. When `battle-voted` fires, both `BattleShow` (to refresh pool stats) and `SidebarWidgets` (if listening) stay in sync via Livewire events — same pattern as today.

## Data flow

- `BattleVoteWidget.mount($battle)` receives the `Battle` and computes `poolA`, `poolB`, `userBalance`, `maxAllowed` (same formula as now), plus `maxVoteAmount` from config.
- Alpine `x-data="voteWidget(...)"` holds reactive `amount`, `side`, `poolA`, `poolB`; derived `percentA`, `percentB`, `multiplier`, `payout`.
- On chip click: `amount = clamp(amount + delta, 1, maxAllowed)`.
- On side click: `side = 'A' | 'B'`; payout re-derives.
- On submit: `$wire.castVote(side, amount)` invokes `BattleVoteWidget::castVote` → `CastVoteAction`. Widget dispatches `battle-voted` and `balance-updated`. `BattleShow` listens via `#[On('battle-voted')]` to refresh its `poolA` / `poolB` / comments snapshot; the widget's own pool mirrors are refreshed from the re-rendered Blade props on the same roundtrip.

## Error, empty, and auth states

Mirrors current behavior, just surfaced in the widget:

- **Not authenticated** — widget shows "Войди, чтобы голосовать" with a login link instead of the form. No side pills, no input.
- **Voting closed** (`!$battle->isOpenForVoting()`) — widget shows "Голосование закрыто" and the settled result if available.
- **Balance == 0** — widget renders but CTA stays disabled with helper text "Недостаточно баланса".
- **Amount out of range** — same clamp behavior + fading amber hint already implemented in the current `voteForm` Alpine component (`amount_clamped` message). Keep it.
- **Server error / validation failure** — Livewire `@error('vote')` under the CTA, styled like the existing red line.

## i18n

All new strings go into `lang/en/battle.php` and `lang/ru/battle.php`:

- `battle.widget.closes_on` — "Завершается {date}" / "Closes {date}"
- `battle.widget.amount_label` — "Сумма" / "Amount" (already exists as `amount_label`, reuse)
- `battle.widget.chip_max` — "Макс" / "Max"
- `battle.widget.payout_preview` — "Если {side} побеждает → {payout} 🪙 (×{multiplier})" / "If {side} wins → {payout} 🪙 (×{multiplier})"
- `battle.widget.cta` — "Проголосовать за {side} · {amount} 🪙" / "Vote for {side} · {amount} 🪙"
- `battle.widget.balance` — "Баланс" / "Balance"
- `battle.widget.distribution` — reuse existing `battle.distribution`.
- `battle.widget.login_required` — "Войди, чтобы голосовать" / "Sign in to vote"
- `battle.widget.voting_closed` — reuse existing `battle.voting_closed`.
- `battle.widget.insufficient_balance` — "Недостаточно баланса" / "Insufficient balance"
- `battle.widget.no_pool_yet` — "—" placeholder used for multiplier when pool is empty.

## Testing

Add a Pest feature test file `tests/Feature/Livewire/BattleVoteWidgetTest.php`:

- Renders for authed user, shows correct percent split from seeded votes.
- Chips add to amount and clamp at `min(balance, max_vote_amount)`.
- `Макс` sets to the clamp.
- `castVote` delegates to `CastVoteAction` (mock/spy) with the chosen side and amount.
- Not-authed user sees the login link, not the form.
- Closed battle hides the form and shows the closed message.
- Insufficient balance disables CTA.

The Alpine-level payout math is client-only; verify it manually once via the browser (we have Playwright MCP available if we want a thin smoke test later, but it's not in scope here).

## Out of scope for this spec

- Sell / exit-before-settlement mechanic.
- Order types (limit/market).
- Price history chart.
- Changing the weight formula or pool math.
- Redesign of `SidebarWidgets` content (only its position relative to the new widget changes).

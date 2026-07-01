# Live Battle Pools Everywhere — Design

**Date:** 2026-07-01
**Status:** Approved (pending spec review)

## Problem

Users should see a battle's prize pool change in real time wherever it appears. Today only the **battle detail page** does this: the `voteBattleDual` Alpine component polls a per-battle endpoint every 5s, reacts to stake events, and plays a `versus-pool-bump` pulse when the value changes.

Everywhere else the pool is a static server-rendered number that only updates on full page reload:

- Home / battle index **featured cards** (`components/battle-featured-card.blade.php`)
- Sidebar **top-battles** widget (`livewire/sidebar-widgets.blade.php`)
- Profile **creations** tab (`livewire/profile/tabs/creation.blade.php`)

Out of scope (explicitly excluded): the search overlay (results are transient), and the battle detail page (already live).

## Decisions

- **Mechanism:** batched HTTP polling. No WebSocket/Reverb infra (none exists today); extends the existing fetch-poll pattern.
- **Visual:** reuse the existing `versus-pool-bump` scale-pulse + number swap. No floating delta, no count-up roll. Cohesive with the battle page.
- **Surfaces:** featured cards, sidebar top-battles, profile creations. Not search.

## Architecture

Three pieces: one backend endpoint, one shared JS ticker, one reusable Alpine component wired into three Blade templates.

### 1. Backend — batched pool endpoint

New controller `App\Http\Controllers\BattlePoolTotalsController` (invokable), route:

```
GET /battles/pool-totals?ids=1,2,3   →   { "1": 12000.00, "2": 8400.00, "3": 500.00 }
```

- Parse `ids` from the query string, cast to ints, drop non-numeric, **dedupe, and cap at 50** ids (silently truncate — these surfaces never show more than ~a dozen at once; the cap is an abuse guard).
- `Battle::whereIn('id', $ids)->pluck('total_pool', 'id')` → map of id → float.
- Missing/unknown ids are simply absent from the response.
- Returns JSON. No auth required (pools are public, same as the existing single-battle endpoint).
- Keep the existing single-battle `BattlePoolTotalController` untouched — the vote widget continues to use it. (Unifying the two is a possible later cleanup, not part of this work.)

### 2. Frontend — shared `PoolTicker` singleton (module-level in `app.js`)

A single module-level object owning **one** `setInterval` (5s), so N visible cards produce **one** request per tick, not N.

- `subscribe(battleId, cb)` → adds `cb` to a `Map<battleId, Set<cb>>`; starts the interval if it was idle; returns an `unsubscribe` handle.
- On each tick: if there are subscribers, collect the unique battle ids, `fetch('/battles/pool-totals?ids=...')`, then for each returned id invoke its subscriber callbacks with the new value.
- When the last subscriber unsubscribes, `clearInterval` and go idle (no polling on pages with no live pools).
- Network errors are swallowed (`.catch(() => {})`), matching the existing poll behavior.
- The route base URL is emitted once into the page (e.g. a `<meta name="pool-totals-url">` or a `window.versus` global) so the JS module can build the query string without hardcoding.

### 3. Frontend — reusable `livePool` Alpine component

`x-data="livePool({ battleId, amount })"`:

- State: `amount` (number).
- `init()`: subscribes to `PoolTicker`; on update, if the rounded value changed, set `amount` and play the bump on `$refs.value`.
- `destroy()`: unsubscribes (Alpine calls this on teardown — covers Livewire morphs, tab switches, and `wire:navigate`).
- Exposes `display` → `Math.round(amount).toLocaleString('en-US')` (comma grouping, matches the current `number_format(..., 0)` output on all three surfaces).
- Bump logic is the same as `voteBattleDual.bumpPoolAmount()` — extract it into a shared helper so both call sites stay identical.

### Blade wiring (per surface)

Each surface wraps its pool number in the component. The **surrounding text/emoji/suffix stays in Blade**; only the number span becomes live. Example (featured card):

```blade
<span class="font-semibold tabular-nums"
      x-data="livePool({ battleId: {{ $battle->id }}, amount: {{ (float) $battle->total_pool }} })">
    💰 <span x-ref="value" x-text="display">{{ number_format((float) $battle->total_pool, 0, '.', ',') }}</span>
    {{ __('battle.tokens') }}
</span>
```

Same pattern for the sidebar row span and the profile creations `VS` sub-line. The server-rendered `number_format` stays as the initial/no-JS fallback inside the `x-ref` span.

## Data flow

```
CastVoteAction commits (any user, anywhere)
        │  (no push — polled)
        ▼
PoolTicker tick (every 5s)
  ── collect visible battle ids from all livePool subscribers
  ── GET /battles/pool-totals?ids=…  → { id: total }
  ── dispatch each total to that id's subscribers
        ▼
livePool.onUpdate(total)
  ── rounded value changed? → amount = total; bump($refs.value)
        ▼
DOM: x-text renders new number, .versus-pool-bump pulses
```

## Edge cases

- **Closed/settled battles:** pool no longer changes, so the poll keeps returning the same value → no bump. Harmless. (These surfaces mostly show active battles anyway.) No special handling.
- **Multiple cards, same battle id** (e.g. same battle in sidebar and a featured card): both subscribe under the same id; both callbacks fire on one fetch. Correct by construction.
- **Empty subscriber set:** interval is cleared; zero network traffic.
- **Tab hidden:** acceptable to keep polling; optional nicety is to skip ticks when `document.hidden`. Include the `document.hidden` guard — cheap and avoids background traffic.
- **Value unchanged:** callback still fires but `livePool` no-ops (rounded compare) — no spurious bump. Matches `voteBattleDual`'s existing guard.

## Testing

- **Feature (Pest):** `GET /battles/pool-totals?ids=…` returns the correct `{id: total}` map; unknown ids omitted; caps at 50 ids; handles empty/garbage `ids` gracefully (returns `{}`); reflects a pool changed via `CastVoteAction`.
- **Manual/visual:** open home in two windows, stake in one, confirm the featured card + sidebar number bump within ~5s in the other; switch profile tabs and confirm no console errors and that polling stops when leaving a page with live pools.
- No JS unit-test harness exists in the repo; the ticker/component are covered by manual verification.

## Files touched

- `app/Http/Controllers/BattlePoolTotalsController.php` (new)
- `routes/web.php` (new route)
- `resources/js/app.js` (`PoolTicker` singleton, `livePool` component, shared bump helper; refactor `voteBattleDual` to use it)
- `resources/views/components/battle-featured-card.blade.php`
- `resources/views/livewire/sidebar-widgets.blade.php`
- `resources/views/livewire/profile/tabs/creation.blade.php`
- Layout head (emit the `pool-totals` URL once)
- `tests/Feature/BattlePoolTotalsTest.php` (new)

No new economic constants, no config changes, no DB changes.

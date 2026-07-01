# Live Battle Pools Everywhere Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show live-updating battle prize pools (with the existing bump pulse) on the home featured cards, the sidebar top-battles widget, and the profile creations tab.

**Architecture:** A new batched JSON endpoint returns `{id: total}` for a list of battle ids. One module-level `PoolTicker` singleton in `app.js` owns a single 5s polling loop shared by every visible pool. A reusable `livePool` Alpine component wraps each pool number, subscribes to the ticker, and replays the existing `versus-pool-bump` animation when its value changes.

**Tech Stack:** Laravel 13, Livewire 4, Alpine.js (bundled with Livewire), Blade, Pest 4, PostgreSQL (dev) / SQLite (test).

## Global Constraints

- All dev/test commands run inside the `workspace` container via the Makefile — never invoke `php`/`artisan`/`composer`/`npm` on the host. Run ad-hoc commands with `make ws` then the command, e.g. `make ws` → `vendor/bin/pest --filter=...`.
- Tests use SQLite `:memory:`; test classes opt into the DB with `use RefreshDatabase;`. Avoid Postgres-only SQL.
- Money is `numeric(20,2)`; round to 2 decimals at every write. This feature only reads pools — no writes.
- Never inline economic percentages/constants — not relevant here (read-only), but keep it in mind.
- Before claiming done: `make pint && make stan && make test` must pass. Run stan with a raised memory limit (default 128M can crash): `make ws` → `vendor/bin/phpstan analyse --memory-limit=512M` (or `npm run stan`).
- New user-facing strings must be added to both `lang/en` and `lang/ru`. This feature adds **no** new strings (numbers only).

---

### Task 1: Batched pool-totals endpoint

**Files:**
- Create: `app/Http/Controllers/BattlePoolTotalsController.php`
- Modify: `routes/web.php` (add route BEFORE the `/battles/{battle:slug}` route on line 20)
- Test: `tests/Feature/BattlePoolTotalsTest.php`

**Interfaces:**
- Consumes: `App\Models\Battle` (has `id`, `total_pool` columns).
- Produces: route named `battles.pool-totals` → `GET /battles/pool-totals?ids=1,2,3` returning a JSON object `{ "1": 12000.0, "2": 8400.0 }`. Unknown ids are omitted. Empty/garbage `ids` → `{}`. Max 50 ids honored (extras dropped).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/BattlePoolTotalsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Battle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattlePoolTotalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_map_of_id_to_total_for_requested_ids(): void
    {
        $a = Battle::factory()->create(['total_pool' => 150.5]);
        $b = Battle::factory()->create(['total_pool' => 900]);

        $response = $this->getJson(route('battles.pool-totals', ['ids' => "{$a->id},{$b->id}"]));

        $response->assertOk()
            ->assertExactJson([
                (string) $a->id => 150.5,
                (string) $b->id => 900,
            ]);
    }

    public function test_omits_unknown_ids(): void
    {
        $a = Battle::factory()->create(['total_pool' => 10]);

        $response = $this->getJson(route('battles.pool-totals', ['ids' => "{$a->id},99999"]));

        $response->assertOk()
            ->assertExactJson([(string) $a->id => 10]);
    }

    public function test_empty_or_garbage_ids_returns_empty_object(): void
    {
        $this->getJson(route('battles.pool-totals', ['ids' => '']))
            ->assertOk()->assertExactJson([]);

        $this->getJson(route('battles.pool-totals', ['ids' => 'abc,-1,0']))
            ->assertOk()->assertExactJson([]);
    }

    public function test_caps_at_fifty_ids(): void
    {
        // 60 non-existent ids must not error; response is just empty (none exist).
        $ids = implode(',', range(1000, 1059));

        $this->getJson(route('battles.pool-totals', ['ids' => $ids]))
            ->assertOk();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `make ws` then `vendor/bin/pest --filter=BattlePoolTotalsTest`
Expected: FAIL — route `battles.pool-totals` not defined (`Symfony\Component\Routing\Exception\RouteNotFoundException`).

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/BattlePoolTotalsController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Battle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BattlePoolTotalsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->take(50)
            ->values();

        if ($ids->isEmpty()) {
            return response()->json((object) []);
        }

        $totals = Battle::query()
            ->whereIn('id', $ids->all())
            ->pluck('total_pool', 'id')
            ->map(fn ($total): float => (float) $total);

        return response()->json($totals->isEmpty() ? (object) [] : $totals);
    }
}
```

- [ ] **Step 4: Register the route**

In `routes/web.php`, add the import near the other controller imports (after line 3):

```php
use App\Http\Controllers\BattlePoolTotalsController;
```

Then add the route immediately BEFORE the `/battles/{battle:slug}` route (currently line 20). It MUST come before the slug route or `pool-totals` is captured as a slug:

```php
Route::get('/battles/pool-totals', BattlePoolTotalsController::class)->name('battles.pool-totals');
```

Resulting order (excerpt):

```php
Route::get('/battles/{battle:slug}/pool-total', BattlePoolTotalController::class)->name('battles.pool-total');
Route::get('/battles/pool-totals', BattlePoolTotalsController::class)->name('battles.pool-totals');
Route::get('/battles/{battle:slug}', BattleShow::class)->name('battles.show');
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `make ws` then `vendor/bin/pest --filter=BattlePoolTotalsTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/BattlePoolTotalsController.php routes/web.php tests/Feature/BattlePoolTotalsTest.php
git commit -m "feat(battle-pool): add batched pool-totals endpoint"
```

---

### Task 2: Emit the pool-totals URL into the layout head

**Files:**
- Modify: `resources/views/layouts/app.blade.php:6` (add a meta tag after the csrf-token meta)

**Interfaces:**
- Consumes: route `battles.pool-totals` from Task 1.
- Produces: `<meta name="pool-totals-url" content="...">` present in the `<head>` of every page using `layouts.app` (all public/Livewire pages). The JS `PoolTicker` (Task 3) reads this.

- [ ] **Step 1: Add the meta tag**

In `resources/views/layouts/app.blade.php`, immediately after line 6 (`<meta name="csrf-token" ...>`), add:

```blade
        <meta name="pool-totals-url" content="{{ route('battles.pool-totals') }}">
```

- [ ] **Step 2: Verify it renders**

Run: `make ws` then `vendor/bin/pest --filter=HomePageTest` (or any existing Home/Feature test that renders a full page). If no such test exists, verify manually: `make art CMD="route:list --name=battles.pool-totals"` prints the route.
Expected: route listed; no errors. (This step only confirms the route resolves for the Blade helper.)

- [ ] **Step 3: Commit**

```bash
git add resources/views/layouts/app.blade.php
git commit -m "feat(battle-pool): expose pool-totals url in layout head"
```

---

### Task 3: PoolTicker singleton + shared bump helper + livePool Alpine component

**Files:**
- Modify: `resources/js/app.js` (add module-level `bumpPoolElement` and `PoolTicker` near the top; register `livePool` inside the existing `alpine:init` handler; refactor `voteBattleDual.bumpPoolAmount` to reuse the helper)

**Interfaces:**
- Consumes: `<meta name="pool-totals-url">` (Task 2); the CSS class `.versus-pool-bump` (already in `resources/css/app.css:23`).
- Produces:
  - module-level `PoolTicker.subscribe(battleId: number, cb: (total: number) => void): () => void` (returns an unsubscribe fn).
  - module-level `bumpPoolElement(el: HTMLElement | null): void`.
  - Alpine component `livePool({ battleId, amount })` exposing `display` (getter, `string`), `init()`, `destroy()`, `onUpdate(total)`, and `$refs.value` as the bump target.

- [ ] **Step 1: Add the shared bump helper and PoolTicker at module level**

In `resources/js/app.js`, add the following AFTER the `import './bootstrap';` line (line 1) and BEFORE the `document.addEventListener('alpine:init', ...)` block (line 3):

```js
function bumpPoolElement(el) {
    if (!el) {
        return;
    }
    el.classList.remove('versus-pool-bump');
    void el.offsetWidth;
    el.addEventListener('animationend', () => {
        el.classList.remove('versus-pool-bump');
    }, { once: true });
    el.classList.add('versus-pool-bump');
}

const PoolTicker = {
    _subs: new Map(), // battleId(number) -> Set<callback>
    _timer: null,
    _intervalMs: 5000,

    _url() {
        const meta = document.querySelector('meta[name="pool-totals-url"]');

        return meta ? meta.getAttribute('content') : '';
    },

    subscribe(battleId, cb) {
        const id = Math.max(0, Number(battleId) || 0);
        if (!id || typeof cb !== 'function') {
            return () => {};
        }
        if (!this._subs.has(id)) {
            this._subs.set(id, new Set());
        }
        this._subs.get(id).add(cb);
        this._start();

        return () => this._unsubscribe(id, cb);
    },

    _unsubscribe(id, cb) {
        const set = this._subs.get(id);
        if (!set) {
            return;
        }
        set.delete(cb);
        if (set.size === 0) {
            this._subs.delete(id);
        }
        if (this._subs.size === 0) {
            this._stop();
        }
    },

    _start() {
        if (this._timer) {
            return;
        }
        this._timer = setInterval(() => this._tick(), this._intervalMs);
    },

    _stop() {
        if (this._timer) {
            clearInterval(this._timer);
            this._timer = null;
        }
    },

    _tick() {
        if (document.hidden) {
            return;
        }
        const ids = Array.from(this._subs.keys());
        if (ids.length === 0) {
            return;
        }
        const base = this._url();
        if (!base) {
            return;
        }
        const url = base + (base.includes('?') ? '&' : '?') + 'ids=' + ids.join(',');
        fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => {
                if (!r.ok) {
                    throw new Error('pool-totals failed');
                }

                return r.json();
            })
            .then((data) => {
                Object.keys(data).forEach((key) => {
                    const set = this._subs.get(Number(key));
                    if (!set) {
                        return;
                    }
                    const total = Math.max(0, Number(data[key]) || 0);
                    set.forEach((cb) => cb(total));
                });
            })
            .catch(() => {});
    },
};
```

- [ ] **Step 2: Register the `livePool` Alpine component**

Inside the existing `document.addEventListener('alpine:init', () => { ... })` block in `resources/js/app.js`, add this registration alongside the other `window.Alpine.data(...)` calls (e.g. right after the `countdown` registration, before `voteWidget`):

```js
    window.Alpine.data('livePool', ({ battleId, amount }) => ({
        battleId: Math.max(0, Number(battleId) || 0),
        amount: Math.max(0, Number(amount) || 0),
        _unsub: null,

        get display() {
            return Math.round(this.amount).toLocaleString('en-US');
        },

        init() {
            if (!this.battleId) {
                return;
            }
            this._unsub = PoolTicker.subscribe(this.battleId, (total) => this.onUpdate(total));
        },

        destroy() {
            if (this._unsub) {
                this._unsub();
                this._unsub = null;
            }
        },

        onUpdate(total) {
            const next = Math.max(0, Number(total) || 0);
            if (Math.round(next) === Math.round(this.amount)) {
                return;
            }
            this.amount = next;
            this.$nextTick(() => bumpPoolElement(this.$refs.value));
        },
    }));
```

- [ ] **Step 3: Refactor `voteBattleDual.bumpPoolAmount` to reuse the helper (DRY)**

In `resources/js/app.js`, replace the body of the existing `bumpPoolAmount()` method (currently lines ~230-241) so it delegates to the shared helper. Find:

```js
        bumpPoolAmount() {
            const el = this.$refs.poolAmount;
            if (!el) {
                return;
            }
            el.classList.remove('versus-pool-bump');
            void el.offsetWidth;
            el.addEventListener('animationend', () => {
                el.classList.remove('versus-pool-bump');
            }, { once: true });
            el.classList.add('versus-pool-bump');
        },
```

Replace with:

```js
        bumpPoolAmount() {
            bumpPoolElement(this.$refs.poolAmount);
        },
```

- [ ] **Step 4: Build assets and verify no errors**

Run: `make npm CMD="run build"`
Expected: Vite build succeeds with no errors. (There is no JS unit-test harness; a clean build is the automated gate. Behavioral verification is Task 5.)

- [ ] **Step 5: Commit**

```bash
git add resources/js/app.js
git commit -m "feat(battle-pool): add shared PoolTicker and livePool alpine component"
```

---

### Task 4: Wire livePool into the three surfaces

**Files:**
- Modify: `resources/views/components/battle-featured-card.blade.php:53-56`
- Modify: `resources/views/livewire/sidebar-widgets.blade.php:21-23`
- Modify: `resources/views/livewire/profile/tabs/creation.blade.php:34-36`

**Interfaces:**
- Consumes: Alpine component `livePool` (Task 3). Each surface's `$battle`/`$t` exposes `id` and `total_pool` (verified: featured card iterates full Battle models; `SidebarWidgets` selects `id,...,total_pool`; `ProfilePage` selects `id,...,total_pool`).
- Produces: no new interface — final wiring.

- [ ] **Step 1: Featured card**

In `resources/views/components/battle-featured-card.blade.php`, replace:

```blade
            <span class="font-semibold tabular-nums">
                💰 {{ number_format((float) $battle->total_pool, 0, '.', ',') }} {{ __('battle.tokens') }}
            </span>
```

with:

```blade
            <span class="font-semibold tabular-nums"
                  x-data="livePool({ battleId: {{ $battle->id }}, amount: {{ (float) $battle->total_pool }} })">
                💰 <span x-ref="value" x-text="display">{{ number_format((float) $battle->total_pool, 0, '.', ',') }}</span> {{ __('battle.tokens') }}
            </span>
```

- [ ] **Step 2: Sidebar top-battles**

In `resources/views/livewire/sidebar-widgets.blade.php`, replace:

```blade
                        <span class="shrink-0 text-xs text-white/50">
                            {{ number_format((float) $t->total_pool, 0) }}
                        </span>
```

with:

```blade
                        <span class="shrink-0 text-xs text-white/50"
                              x-data="livePool({ battleId: {{ $t->id }}, amount: {{ (float) $t->total_pool }} })">
                            <span x-ref="value" x-text="display">{{ number_format((float) $t->total_pool, 0) }}</span>
                        </span>
```

- [ ] **Step 3: Profile creations tab**

In `resources/views/livewire/profile/tabs/creation.blade.php`, replace:

```blade
                                <div class="mt-1 text-xs text-white/70">
                                    {{ number_format((float) $battle->total_pool, 0) }} {{ __('profile.activity_vrs') }}
                                </div>
```

with:

```blade
                                <div class="mt-1 text-xs text-white/70"
                                     x-data="livePool({ battleId: {{ $battle->id }}, amount: {{ (float) $battle->total_pool }} })">
                                    <span x-ref="value" x-text="display">{{ number_format((float) $battle->total_pool, 0) }}</span> {{ __('profile.activity_vrs') }}
                                </div>
```

- [ ] **Step 4: Build assets**

Run: `make npm CMD="run build"`
Expected: build succeeds.

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/battle-featured-card.blade.php resources/views/livewire/sidebar-widgets.blade.php resources/views/livewire/profile/tabs/creation.blade.php
git commit -m "feat(battle-pool): make pools live on cards, sidebar, and profile"
```

---

### Task 5: Full gate + manual visual verification

**Files:** none (verification only).

**Interfaces:** none.

- [ ] **Step 1: Run the CI-equivalent gate**

Run inside `make ws`:
```
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=512M
php artisan test
```
Expected: Pint clean (run `vendor/bin/pint` to auto-fix if not), PHPStan level 6 clean, all Pest tests pass including `BattlePoolTotalsTest`.

- [ ] **Step 2: Manual two-window check**

With `make up` running and (in a separate shell) `make art CMD=schedule:work` not required for this check:
1. Open http://versus.local/ in two browser windows, logged in as different seeded users if possible.
2. In window A, open a battle and stake tokens.
3. In window B (home), within ~5s confirm the matching featured card's pool number updates and pulses. Confirm the sidebar top-battles number updates too (if that battle is in the top list).
4. Open a profile creations tab for a user whose battle just received a stake; confirm its pool updates within ~5s.
5. Open DevTools Network: confirm exactly ONE `/battles/pool-totals?ids=...` request per ~5s while pools are visible, and that requests STOP after navigating to a page with no live pools (e.g. wallet).
6. Switch to another browser tab and back: confirm no polling while the tab is hidden (no requests fire), and it resumes on return.

Expected: all confirmations hold; no console errors.

- [ ] **Step 3: Final commit (only if fixes were needed)**

If Step 1 or 2 required changes, commit them:
```bash
git add -A
git commit -m "fix(battle-pool): address verification findings"
```

---

## Self-Review Notes

- **Spec coverage:** batched endpoint (Task 1), URL exposure (Task 2), PoolTicker singleton + livePool + shared bump + voteBattleDual refactor (Task 3), three surfaces wired (Task 4), tests + manual checks incl. tab-hidden guard and single-request-per-tick (Tasks 1 & 5). Search overlay and battle detail page intentionally excluded per spec.
- **Type consistency:** `PoolTicker.subscribe(battleId, cb) → unsubscribe fn`, `bumpPoolElement(el)`, `livePool` `display` getter and `$refs.value` bump target are used consistently across Tasks 3 and 4.
- **No new i18n strings** (numbers only) — Global Constraints satisfied.

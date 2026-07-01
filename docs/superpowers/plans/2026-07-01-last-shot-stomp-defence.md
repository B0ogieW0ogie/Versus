# LAST SHOT + Stomp Defence Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add two settlement-time battle mechanics — LAST SHOT (a 50-50 battle hangs open until the next vote decides it) and Stomp Defence (a ≥90% supermajority voids the battle and refunds every stake).

**Architecture:** Both conditions are evaluated only at settlement inside `SettleBattleAction`, in the order Empty → Stomp → 50-50 → winner. A 50-50 battle transitions to a new `last_shot` status instead of settling; `CastVoteAction` detects a vote on such a battle and settles it immediately. A `void_reason` column records why a battle was voided. Frontend surfaces a flashing LAST SHOT label in the HOT rail and a Stomp void plate.

**Tech Stack:** Laravel 13, Livewire 4, Blade, Tailwind, PostgreSQL 16 (dev) / SQLite (test), Pest 4.

## Global Constraints

- Run every dev command through the workspace container via the Makefile. Single test: `make ws` then `vendor/bin/pest --filter=<name>`.
- Keep economic constants in `config/versus.php` — never inline percentages/thresholds.
- Wrap money mutations in `DB::transaction` + `lockForUpdate` on contested rows.
- Round money to 2 decimals at every write (`round($value, 2)`).
- Prefer model constants (`Battle::STATUS_*`, `Battle::SIDE_*`, `Transaction::TYPE_*`) over literals.
- Add every user-facing string to both `lang/en/` and `lang/ru/`.
- Tests use `use RefreshDatabase;` on the class; avoid Postgres-only SQL.
- CI gate before claiming done: `make pint && make stan && make test`.

---

### Task 1: Battle state model — `last_shot` status, `void_reason`, open-for-voting

**Files:**
- Create: `database/migrations/2026_07_01_000000_add_void_reason_to_battles_table.php`
- Modify: `app/Models/Battle.php` (Fillable attribute block ~21-28; add constants ~44; `isOpenForVoting()` ~129-135)
- Test: `tests/Feature/Battles/BattleStateTest.php`

**Interfaces:**
- Produces: `Battle::STATUS_LAST_SHOT = 'last_shot'`, `Battle::VOID_EMPTY = 'empty'`, `Battle::VOID_STOMP = 'stomp'`, nullable `battles.void_reason` string column, and `isOpenForVoting()` returning `true` for a `last_shot` battle.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Battles/BattleStateTest.php`:

```php
<?php

namespace Tests\Feature\Battles;

use App\Models\Battle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattleStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_shot_battle_is_open_for_voting_despite_expired_timer(): void
    {
        $battle = Battle::factory()->create([
            'status' => Battle::STATUS_LAST_SHOT,
            'closes_at' => now()->subHour(),
        ]);

        $this->assertTrue($battle->isOpenForVoting());
    }

    public function test_void_reason_is_persisted(): void
    {
        $battle = Battle::factory()->create(['void_reason' => Battle::VOID_STOMP]);

        $this->assertSame(Battle::VOID_STOMP, $battle->fresh()->void_reason);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `make ws` then `vendor/bin/pest --filter=BattleStateTest`
Expected: FAIL — `Undefined constant Battle::STATUS_LAST_SHOT` / unknown column `void_reason`.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_01_000000_add_void_reason_to_battles_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battles', function (Blueprint $table) {
            $table->string('void_reason')->nullable()->after('winning_side');
        });
    }

    public function down(): void
    {
        Schema::table('battles', function (Blueprint $table) {
            $table->dropColumn('void_reason');
        });
    }
};
```

- [ ] **Step 4: Add constants, fillable entry, and open-for-voting rule**

In `app/Models/Battle.php`, add `'void_reason'` to the `#[Fillable([...])]` list (append after `'ai_screened_at'`).

Add constants alongside the existing status constants (after `STATUS_SETTLED`):

```php
    public const STATUS_LAST_SHOT = 'last_shot';

    public const VOID_EMPTY = 'empty';

    public const VOID_STOMP = 'stomp';
```

Replace `isOpenForVoting()` with:

```php
    public function isOpenForVoting(): bool
    {
        if ($this->status === self::STATUS_LAST_SHOT) {
            return true;
        }

        return $this->status === self::STATUS_ACTIVE
            && $this->closes_at !== null
            && $this->closes_at->isFuture()
            && ($this->opens_at === null || ! $this->opens_at->isFuture());
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest --filter=BattleStateTest`
Expected: PASS (2 tests). Migration runs automatically under `RefreshDatabase`.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_01_000000_add_void_reason_to_battles_table.php app/Models/Battle.php tests/Feature/Battles/BattleStateTest.php
git commit -m "feat(battle): add last_shot status and void_reason column"
```

---

### Task 2: Settlement — Stomp Defence, LAST SHOT, void_reason

**Files:**
- Modify: `config/versus.php` (add `mechanics` block)
- Modify: `app/Actions/Battles/SettleBattleAction.php` (`__invoke` head ~31-52; `refundAll` meta ~153)
- Test: `tests/Feature/SettlementTest.php` (add new tests; rewrite `test_tie_refunds_all_stakes`)

**Interfaces:**
- Consumes: `Battle::STATUS_LAST_SHOT`, `Battle::VOID_EMPTY`, `Battle::VOID_STOMP` (Task 1); `config('versus.mechanics.stomp_threshold')`.
- Produces: settlement that returns a `last_shot` battle for exact 50-50, a `settled` + `void_reason=stomp` battle with full refund for ≥90% supermajority, and stamps `void_reason=empty` for empty pools.

- [ ] **Step 1: Add the config constant**

In `config/versus.php`, add a top-level block (after the `referral` block):

```php
    'mechanics' => [
        'stomp_threshold' => 0.90, // side share at/above which the battle is void
    ],
```

- [ ] **Step 2: Write the failing tests**

In `tests/Feature/SettlementTest.php`, **replace** `test_tie_refunds_all_stakes` with the following, and add the other new methods after it:

```php
    public function test_fifty_fifty_enters_last_shot_without_settling(): void
    {
        $a = User::factory()->create(['balance' => 1000]);
        $b = User::factory()->create(['balance' => 1000]);

        $battle = Battle::factory()->create();

        ($this->vote())($a, $battle, Battle::SIDE_A, 100);
        ($this->vote())($b, $battle, Battle::SIDE_B, 100);

        $settled = $this->closeAndSettle($battle);

        $this->assertSame(Battle::STATUS_LAST_SHOT, $settled->status);
        $this->assertNull($settled->settled_at);
        $this->assertNull($settled->winning_side);
        $this->assertTrue($settled->fresh()->isOpenForVoting());

        // stakes are held, not refunded
        $this->assertSame(900.0, (float) $a->fresh()->balance);
        $this->assertSame(900.0, (float) $b->fresh()->balance);
    }

    public function test_stomp_defence_voids_and_refunds_in_full(): void
    {
        $a = User::factory()->create(['balance' => 1000]);
        $b = User::factory()->create(['balance' => 1000]);

        $battle = Battle::factory()->create();

        ($this->vote())($a, $battle, Battle::SIDE_A, 950);
        ($this->vote())($b, $battle, Battle::SIDE_B, 50);

        $settled = $this->closeAndSettle($battle);

        $this->assertSame(Battle::STATUS_SETTLED, $settled->status);
        $this->assertSame(Battle::VOID_STOMP, $settled->void_reason);
        $this->assertNull($settled->winning_side);

        // full 100% refund
        $this->assertSame(1000.0, (float) $a->fresh()->balance);
        $this->assertSame(1000.0, (float) $b->fresh()->balance);

        // no fees taken
        $this->assertDatabaseMissing('transactions', [
            'type' => Transaction::TYPE_PROJECT_FEE,
            'battle_id' => $battle->id,
        ]);
        $this->assertDatabaseMissing('transactions', [
            'type' => Transaction::TYPE_BURN,
            'battle_id' => $battle->id,
        ]);
    }

    public function test_stomp_triggers_exactly_at_threshold(): void
    {
        $a = User::factory()->create(['balance' => 1000]);
        $b = User::factory()->create(['balance' => 1000]);

        $battle = Battle::factory()->create();

        ($this->vote())($a, $battle, Battle::SIDE_A, 900); // 900/1000 = 0.90
        ($this->vote())($b, $battle, Battle::SIDE_B, 100);

        $settled = $this->closeAndSettle($battle);

        $this->assertSame(Battle::VOID_STOMP, $settled->void_reason);
    }

    public function test_just_below_threshold_settles_with_a_winner(): void
    {
        $a = User::factory()->create(['balance' => 1000]);
        $b = User::factory()->create(['balance' => 1000]);

        $battle = Battle::factory()->create();

        ($this->vote())($a, $battle, Battle::SIDE_A, 800); // 800/1000 = 0.80
        ($this->vote())($b, $battle, Battle::SIDE_B, 200);

        $settled = $this->closeAndSettle($battle);

        $this->assertSame(Battle::STATUS_SETTLED, $settled->status);
        $this->assertSame('A', $settled->winning_side);
        $this->assertNull($settled->void_reason);
    }
```

Also update `test_battle_with_no_votes_settles_with_zero_pool` to assert the reason:

```php
        $this->assertSame(Battle::VOID_EMPTY, $settled->void_reason);
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `vendor/bin/pest --filter=SettlementTest`
Expected: FAIL — 50-50 still refunds/settles; stomp not detected; `void_reason` null.

- [ ] **Step 4: Restructure the settlement head**

In `app/Actions/Battles/SettleBattleAction.php`, replace the block that runs from the empty-pool check through the system-share credits (current lines 33-52 — the `if ($pool <= 0) { ... }` block, the four `$dist`/`$projectShare`/…/`systemCredit` lines, and the `$winningSide = $this->decideWinner(...)` line) with:

```php
            if ($pool <= 0) {
                $battle->status = Battle::STATUS_SETTLED;
                $battle->settled_at = now();
                $battle->total_pool = 0;
                $battle->void_reason = Battle::VOID_EMPTY;
                $battle->save();

                return $battle;
            }

            $totalWeight = $weightA + $weightB;
            $maxSide = max($weightA, $weightB);
            $stompThreshold = (float) config('versus.mechanics.stomp_threshold');

            if ($totalWeight > 0 && $maxSide / $totalWeight >= $stompThreshold) {
                $this->refundAll($battle);

                $battle->status = Battle::STATUS_SETTLED;
                $battle->settled_at = now();
                $battle->total_pool = $pool;
                $battle->void_reason = Battle::VOID_STOMP;
                $battle->save();

                return $battle;
            }

            if ($weightA === $weightB && $weightA > 0.0) {
                $battle->status = Battle::STATUS_LAST_SHOT;
                $battle->save();

                return $battle;
            }

            $dist = (array) config('versus.distribution');
            $projectShare = $this->round($pool * (float) $dist['project']);
            $burnShare = $this->round($pool * (float) $dist['burn']);
            $rewardPoolShare = $this->round($pool * (float) $dist['reward_pool']);
            $winnersShare = $this->round($pool * (float) $dist['winners']);

            $this->systemCredit(Transaction::TYPE_PROJECT_FEE, $projectShare, $battle->id);
            $this->systemCredit(Transaction::TYPE_BURN, $burnShare, $battle->id);
            $this->systemCredit(Transaction::TYPE_REWARD_POOL_CREDIT, $rewardPoolShare, $battle->id);

            $winningSide = $this->decideWinner($weightA, $weightB);
```

The rest of `__invoke` (the `$winningSide === null` branch onward) stays unchanged. In `refundAll`, change the meta reason from `'tie_refund'` to `'stomp_refund'`:

```php
                'meta' => ['vote_id' => $vote->id, 'reason' => 'stomp_refund'],
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest --filter=SettlementTest`
Expected: PASS (all methods, including the rewritten 50-50 and empty-pool tests).

- [ ] **Step 6: Commit**

```bash
git add config/versus.php app/Actions/Battles/SettleBattleAction.php tests/Feature/SettlementTest.php
git commit -m "feat(settle): Stomp Defence void and LAST SHOT on exact 50-50"
```

---

### Task 3: Tie-break vote settles a LAST SHOT battle immediately

**Files:**
- Modify: `app/Actions/Battles/CastVoteAction.php` (capture status before write ~27; settle after write ~83)
- Test: `tests/Feature/SettlementTest.php` (add tie-break test)

**Interfaces:**
- Consumes: `Battle::STATUS_LAST_SHOT` (Task 1); `SettleBattleAction` (Task 2).
- Produces: casting a vote on a `last_shot` battle records the vote, then settles the battle in the same transaction with the voted side winning.

- [ ] **Step 1: Write the failing test**

In `tests/Feature/SettlementTest.php`, add:

```php
    public function test_last_shot_next_vote_wins_and_settles_immediately(): void
    {
        $a = User::factory()->create(['balance' => 1000]);
        $b = User::factory()->create(['balance' => 1000]);

        $battle = Battle::factory()->create();

        ($this->vote())($a, $battle, Battle::SIDE_A, 100);
        ($this->vote())($b, $battle, Battle::SIDE_B, 100);

        $lastShot = $this->closeAndSettle($battle);
        $this->assertSame(Battle::STATUS_LAST_SHOT, $lastShot->status);

        // the guaranteed-victory shot
        $c = User::factory()->create(['balance' => 1000]);
        ($this->vote())($c, $lastShot->fresh(), Battle::SIDE_A, 50);

        $battle->refresh();
        $this->assertSame(Battle::STATUS_SETTLED, $battle->status);
        $this->assertSame('A', $battle->winning_side);

        // pool 250, winners share 88% = 220, side A weight 150 (a=100, c=50)
        // a: 900 + round(220*100/150) = 900 + 146.67
        // c: 950 + residue (220 - 146.67) = 950 + 73.33
        $this->assertSame(1046.67, (float) $a->fresh()->balance);
        $this->assertSame(1023.33, (float) $c->fresh()->balance);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest --filter=test_last_shot_next_vote_wins`
Expected: FAIL — battle stays `last_shot`, `winning_side` null (vote does not trigger settlement yet).

- [ ] **Step 3: Trigger immediate settlement in CastVoteAction**

In `app/Actions/Battles/CastVoteAction.php`, capture the pre-write status. Immediately after the line `$battle = Battle::whereKey($battle->id)->lockForUpdate()->firstOrFail();` inside the transaction (and after the `isOpenForVoting()` guard), add:

```php
            $wasLastShot = $battle->status === Battle::STATUS_LAST_SHOT;
```

Then replace the final `return $vote;` inside the transaction with:

```php
            if ($wasLastShot) {
                app(SettleBattleAction::class)($battle);
            }

            return $vote;
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest --filter=SettlementTest`
Expected: PASS (including the new tie-break test and all Task 2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Actions/Battles/CastVoteAction.php tests/Feature/SettlementTest.php
git commit -m "feat(vote): LAST SHOT vote settles battle immediately for its side"
```

---

### Task 4: HOT rail includes LAST SHOT battles, pinned first

**Files:**
- Modify: `app/Livewire/BattleIndex.php` (`$hot` query ~19-25)
- Test: `tests/Feature/Battles/BattleIndexHotTest.php`

**Interfaces:**
- Consumes: `Battle::STATUS_LAST_SHOT` (Task 1).
- Produces: the `hot` view data includes `last_shot` battles ordered ahead of active ones.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Battles/BattleIndexHotTest.php`:

```php
<?php

namespace Tests\Feature\Battles;

use App\Livewire\BattleIndex;
use App\Models\Battle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BattleIndexHotTest extends TestCase
{
    use RefreshDatabase;

    public function test_hot_pins_last_shot_battles_ahead_of_active(): void
    {
        $active = Battle::factory()->create([
            'status' => Battle::STATUS_ACTIVE,
            'total_pool' => 5000,
            'is_sponsored' => false,
        ]);
        $lastShot = Battle::factory()->create([
            'status' => Battle::STATUS_LAST_SHOT,
            'total_pool' => 10,
            'is_sponsored' => false,
        ]);

        Livewire::test(BattleIndex::class)
            ->assertViewHas('hot', function ($hot) use ($active, $lastShot) {
                return $hot->contains(fn ($b) => $b->is($lastShot))
                    && $hot->contains(fn ($b) => $b->is($active))
                    && $hot->first()->is($lastShot);
            });
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest --filter=BattleIndexHotTest`
Expected: FAIL — `hot` uses the `active()` scope and excludes the `last_shot` battle.

- [ ] **Step 3: Widen and reorder the query**

In `app/Livewire/BattleIndex.php`, replace the `$hot` query with:

```php
        $hot = Battle::query()
            ->whereIn('status', [Battle::STATUS_ACTIVE, Battle::STATUS_LAST_SHOT])
            ->with('category')
            ->when($sponsoredIds, fn ($q) => $q->whereNotIn('id', $sponsoredIds))
            ->orderByRaw('case when status = ? then 0 else 1 end', [Battle::STATUS_LAST_SHOT])
            ->orderByDesc('total_pool')
            ->limit(10)
            ->get();
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest --filter=BattleIndexHotTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/BattleIndex.php tests/Feature/Battles/BattleIndexHotTest.php
git commit -m "feat(home): pin LAST SHOT battles to top of HOT rail"
```

---

### Task 5: Frontend — flashing LAST SHOT label, Stomp void plate, i18n

**Files:**
- Modify: `resources/views/components/battle-tile.blade.php` (@php block; timer span)
- Modify: `resources/views/livewire/battle-vote-widget.blade.php` (closed branch ~9-12; open branch top ~13)
- Modify: `lang/en/battle.php`, `lang/ru/battle.php`

**Interfaces:**
- Consumes: `Battle::STATUS_LAST_SHOT`, `Battle::VOID_STOMP`, `void_reason` (Task 1).
- Produces: visible LAST SHOT flashing label on tiles + battle page, and a Stomp void message.

- [ ] **Step 1: Add i18n keys (both locales)**

In `lang/en/battle.php`, add before the closing `];`:

```php
    'last_shot' => 'LAST SHOT',
    'last_shot_hint' => 'Next vote decides the winner',
    'stomp_defence' => 'Stomp Defence',
    'void_refunded' => 'Battle void — all stakes refunded',
```

In `lang/ru/battle.php`, add before the closing `];`:

```php
    'last_shot' => 'LAST SHOT',
    'last_shot_hint' => 'Следующая ставка решает исход',
    'stomp_defence' => 'Stomp Defence',
    'void_refunded' => 'Баттл несостоялся — ставки возвращены',
```

- [ ] **Step 2: Flashing LAST SHOT label on the battle tile**

In `resources/views/components/battle-tile.blade.php`, inside the `@php` block add after `$sideBLabel = ...`:

```php
    $isLastShot = $battle->status === \App\Models\Battle::STATUS_LAST_SHOT;
```

Replace the timer span:

```blade
        <span class="text-right">⏱ {{ $timeLabel }}</span>
```

with:

```blade
        <span class="text-right">
            @if ($isLastShot)
                <span class="animate-pulse font-bold uppercase tracking-wide text-glow-cyan">🎯 {{ __('battle.last_shot') }}</span>
            @else
                ⏱ {{ $timeLabel }}
            @endif
        </span>
```

- [ ] **Step 3: LAST SHOT banner + Stomp plate in the vote widget**

In `resources/views/livewire/battle-vote-widget.blade.php`, replace the closed branch:

```blade
        @if (! $battle->isOpenForVoting())
            <div class="border-t border-white/10 bg-navy-950/80 px-4 py-6 text-center text-sm text-white/70">
                {{ __('battle.voting_closed') }}
            </div>
        @else
```

with:

```blade
        @if (! $battle->isOpenForVoting())
            <div class="border-t border-white/10 bg-navy-950/80 px-4 py-6 text-center text-sm text-white/70">
                @if ($battle->void_reason === \App\Models\Battle::VOID_STOMP)
                    <span class="font-semibold text-white/90">🛡 {{ __('battle.stomp_defence') }}</span>
                    <span class="block text-white/60">{{ __('battle.void_refunded') }}</span>
                @else
                    {{ __('battle.voting_closed') }}
                @endif
            </div>
        @else
            @if ($battle->status === \App\Models\Battle::STATUS_LAST_SHOT)
                <div class="border-t border-glow-cyan/40 bg-navy-950/90 px-4 py-3 text-center">
                    <span class="animate-pulse text-sm font-bold uppercase tracking-wide text-glow-cyan">🎯 {{ __('battle.last_shot') }}</span>
                    <span class="block text-xs text-white/60">{{ __('battle.last_shot_hint') }}</span>
                </div>
            @endif
```

- [ ] **Step 4: Verify rendering manually**

Run: `make up` (if not running), then create the states via tinker: `make art CMD="tinker"` and set a battle's `status` to `last_shot` and another's `void_reason` to `stomp`. Load http://versus.local/ and the battle page. Confirm the flashing LAST SHOT label appears in the HOT rail and on the battle page, and the Stomp void plate shows on a stomped battle.

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/battle-tile.blade.php resources/views/livewire/battle-vote-widget.blade.php lang/en/battle.php lang/ru/battle.php
git commit -m "feat(ui): flashing LAST SHOT label and Stomp Defence void plate"
```

---

### Task 6: Full gate + final commit

**Files:** none (verification only)

- [ ] **Step 1: Run the CI-equivalent gate**

Run: `make pint && make stan && make test`
Expected: pint clean, Larastan level 6 clean (use `--memory-limit=512M` if it OOMs), full Pest suite green.

- [ ] **Step 2: Fix any fallout**

If `make stan` flags the new `void_reason` property, add `@property string|null $void_reason` to the Battle model docblock. If pint reformats, re-commit.

- [ ] **Step 3: Commit any gate fixes**

```bash
git add -A
git commit -m "chore: satisfy pint/stan for last-shot + stomp mechanics"
```

---

## Self-Review

**Spec coverage:**
- LAST SHOT status + indefinite hang → Task 1 (status/open-for-voting), Task 2 (50-50 → last_shot), Task 3 (next vote settles). ✓
- Stomp Defence ≥90% full refund no fees → Task 2. ✓
- Precedence Empty → Stomp → 50-50 → winner → Task 2 ordering. ✓
- void_reason distinguishing empty/stomp → Task 1 column, Task 2 stamping. ✓
- config-driven threshold → Task 2 `versus.mechanics.stomp_threshold`. ✓
- LAST SHOT in HOT + flashing label → Task 4 (query), Task 5 (label). ✓
- Stomp void plate → Task 5. ✓
- i18n both locales → Task 5. ✓
- Tests incl. updated tie test → Tasks 1-4. ✓

**Placeholder scan:** No TBD/TODO; every code step shows full content. ✓

**Type consistency:** `Battle::STATUS_LAST_SHOT`, `Battle::VOID_EMPTY`, `Battle::VOID_STOMP`, `void_reason`, `config('versus.mechanics.stomp_threshold')`, `SettleBattleAction` used consistently across tasks. ✓

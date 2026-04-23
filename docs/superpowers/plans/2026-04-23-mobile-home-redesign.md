# Mobile home redesign — implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the home page around a sponsored-battle slider, per-category rails, and a 5-tab mobile bottom nav with a central FAB. Dark theme retained, desktop keeps sidebar.

**Architecture:** Backend-side: replace the single-featured concept (`is_featured` flag + `resolveFeatured()`) with real sponsorship (`is_sponsored` + `sponsor_handle`). `BattleIndex` becomes a thin component that hands off `sponsored` / `hot` / `categoryRails` collections to a carousel-heavy view. A new `<x-carousel>` Alpine+Blade component is shared across all rails and the featured slider. A new `CategoryShow` Livewire page provides the "View all" destination. Category chips + unified "all battles" list + finished filter are removed.

**Tech Stack:** Laravel 13, Livewire 4, Filament 5, Blade, Alpine.js (already bundled), Tailwind, PostgreSQL 16 (dev) / SQLite `:memory:` (tests), Pest 4, Larastan.

**Specification:** [docs/superpowers/specs/2026-04-23-mobile-home-redesign-design.md](../specs/2026-04-23-mobile-home-redesign-design.md)

---

## Repo conventions worth knowing

- All PHP/JS commands run in the Docker `workspace` container — prefer `make ws` then `vendor/bin/pest --filter=...`, or `make test` for the full suite.
- Test DB is SQLite `:memory:` (see `phpunit.xml`). `RefreshDatabase` trait is opt-in per test class.
- Tests use the PHPUnit class-based style (`class FooTest extends TestCase { public function test_bar(): void {...} }`), not Pest's `test()` functions — follow the repo's convention.
- The seed migration `2026_04_22_130000_seed_home_page_demo_data.php` skips in `testing` env, so tests see an empty DB.
- Pint is the formatter, Larastan (level 6) the static analyzer. Gate before claiming done: `make pint && make stan && make test`.
- Livewire `assertViewHas` supports value-or-callback comparisons — prefer callbacks for collections.

---

## File map

### Created

- `database/migrations/2026_04_23_100000_add_sponsorship_to_battles_table.php` — add `is_sponsored`, `sponsor_handle`; mark a demo battle sponsored; drop `is_featured`.
- `tests/Feature/Battle/SponsorshipTest.php` — `Battle::sponsoredActive()` + `compactPool()`.
- `tests/Feature/Home/HomePageSectionsTest.php` — home render (sponsored slider, HOT, category rails).
- `tests/Feature/Categories/CategoryShowTest.php` — category page.
- `tests/Feature/Navigation/BottomNavTest.php` — bottom nav tabs.
- `app/Livewire/CategoryShow.php` — category page Livewire.
- `resources/views/livewire/category-show.blade.php` — category page view.
- `resources/views/components/carousel.blade.php` — shared Alpine carousel.
- `resources/views/components/battle-tile.blade.php` — rail tile.
- `resources/views/components/battle-featured-card.blade.php` — sponsored slide card.
- `resources/views/components/icon/image-placeholder.blade.php`
- `resources/views/components/icon/plus.blade.php`
- `resources/views/components/icon/feed.blade.php`

### Modified

- `app/Models/Battle.php` — `$fillable` + casts, add `sponsoredActive()`, add `compactPool()`, remove `scopeFeatured()`, remove `resolveFeatured()`.
- `database/factories/BattleFactory.php` — swap `is_featured` default for sponsorship fields, add `sponsored()` state.
- `app/Filament/Admin/Resources/Battles/Schemas/BattleForm.php` — replace `Toggle::make('is_featured')` with sponsorship controls.
- `app/Livewire/BattleIndex.php` — full rewrite: no pagination, no category/finished URL state, three collections.
- `resources/views/livewire/battle-index.blade.php` — full rewrite around carousels.
- `resources/views/layouts/bottom-nav.blade.php` — 5 tabs + FAB.
- `resources/js/app.js` — register `Alpine.data('carousel', ...)`.
- `routes/web.php` — add `/categories/{category:slug}`.
- `lang/en/nav.php`, `lang/ru/nav.php` — `feed`, `create`, `coming_soon`.
- `lang/en/battle.php`, `lang/ru/battle.php` — add `sponsored_by`, `no_active_in_category`; remove dead keys.

### Deleted

- `app/Models/Battle.php` — `scopeFeatured()` + `resolveFeatured()` (inline in Task 1).
- `tests/Feature/FeaturedBattleTest.php` — obsolete.
- `tests/Feature/Livewire/BattleIndexTest.php` — obsolete (replaced by `HomePageSectionsTest`).
- `tests/Feature/BattleScopesTest.php` — feature test `test_featured_scope_returns_only_flagged_battles` removed (inline in Task 1).
- `resources/views/livewire/battle-index/featured-card.blade.php`
- `resources/views/livewire/battle-index/hot-rail.blade.php`
- `resources/views/livewire/battle-index/category-chips.blade.php`
- `resources/views/livewire/battle-index/all-list.blade.php`
- `resources/views/components/battle-row.blade.php`

---

## Task 1: Add sponsorship fields; remove `is_featured` concept

**Files:**
- Create: `database/migrations/2026_04_23_100000_add_sponsorship_to_battles_table.php`
- Create: `tests/Feature/Battle/SponsorshipTest.php`
- Modify: `app/Models/Battle.php`
- Modify: `database/factories/BattleFactory.php`
- Modify: `app/Filament/Admin/Resources/Battles/Schemas/BattleForm.php`
- Modify: `tests/Feature/BattleScopesTest.php`
- Delete: `tests/Feature/FeaturedBattleTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Battle/SponsorshipTest.php`:

```php
<?php

namespace Tests\Feature\Battle;

use App\Models\Battle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SponsorshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_sponsored_active_returns_only_active_sponsored_battles(): void
    {
        Battle::factory()->create(['is_sponsored' => true]);
        Battle::factory()->create(['is_sponsored' => false]);
        Battle::factory()->settled()->create(['is_sponsored' => true]);
        Battle::factory()->draft()->create(['is_sponsored' => true]);

        $result = Battle::sponsoredActive();

        $this->assertCount(1, $result);
    }

    public function test_sponsored_active_orders_by_closes_at_ascending(): void
    {
        $later = Battle::factory()->create([
            'is_sponsored' => true,
            'closes_at' => now()->addDays(5),
        ]);
        $sooner = Battle::factory()->create([
            'is_sponsored' => true,
            'closes_at' => now()->addDays(1),
        ]);

        $result = Battle::sponsoredActive();

        $this->assertTrue($result->first()->is($sooner));
        $this->assertTrue($result->last()->is($later));
    }

    public function test_sponsored_active_limits_to_ten(): void
    {
        Battle::factory()->count(12)->create(['is_sponsored' => true]);

        $this->assertCount(10, Battle::sponsoredActive());
    }

    public function test_compact_pool_formats_thousands_with_k(): void
    {
        $b = Battle::factory()->make(['total_pool' => 45000]);
        $this->assertSame('45k', $b->compactPool());
    }

    public function test_compact_pool_formats_non_round_thousands_with_one_decimal(): void
    {
        $b = Battle::factory()->make(['total_pool' => 1500]);
        $this->assertSame('1.5k', $b->compactPool());
    }

    public function test_compact_pool_returns_plain_number_under_thousand(): void
    {
        $b = Battle::factory()->make(['total_pool' => 420]);
        $this->assertSame('420', $b->compactPool());
    }

    public function test_is_sponsored_casts_to_boolean(): void
    {
        $b = Battle::factory()->create(['is_sponsored' => true]);
        $this->assertSame(true, $b->fresh()->is_sponsored);
    }

    public function test_sponsor_handle_is_fillable(): void
    {
        $b = Battle::factory()->create(['sponsor_handle' => '@brand']);
        $this->assertSame('@brand', $b->fresh()->sponsor_handle);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```
make ws
vendor/bin/pest tests/Feature/Battle/SponsorshipTest.php
```

Expected: errors about unknown column `is_sponsored`, method `sponsoredActive` missing, method `compactPool` missing.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_04_23_100000_add_sponsorship_to_battles_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battles', function (Blueprint $table) {
            $table->boolean('is_sponsored')->default(false)->index();
            $table->string('sponsor_handle')->nullable();
        });

        // Promote a demo battle if the seed data is present (skipped in tests).
        if (! app()->environment('testing')) {
            DB::table('battles')
                ->where('slug', 'messi-vs-ronaldo')
                ->update(['is_sponsored' => true, 'sponsor_handle' => '@brand']);
        }

        Schema::table('battles', function (Blueprint $table) {
            $table->dropColumn('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('battles', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->index();
        });

        Schema::table('battles', function (Blueprint $table) {
            $table->dropColumn(['is_sponsored', 'sponsor_handle']);
        });
    }
};
```

- [ ] **Step 4: Update `Battle` model**

Modify `app/Models/Battle.php`. In the `#[Fillable([...])]` list, remove `'is_featured'` and add `'is_sponsored'` and `'sponsor_handle'`. In `casts()`, replace `'is_featured' => 'boolean'` with `'is_sponsored' => 'boolean'`. Remove `scopeFeatured()` and `resolveFeatured()` entirely. Add `sponsoredActive()` and `compactPool()`.

Final state of the model (relevant parts):

```php
#[Fillable([
    'slug',
    'title',
    'description',
    'side_a_label',
    'side_b_label',
    'side_a_subtitle',
    'side_b_subtitle',
    'side_a_image',
    'side_b_image',
    'status',
    'opens_at',
    'closes_at',
    'winning_side',
    'total_pool',
    'created_by_id',
    'settled_at',
    'category_id',
    'is_sponsored',
    'sponsor_handle',
])]
class Battle extends Model
{
    // ... constants unchanged ...

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'settled_at' => 'datetime',
            'total_pool' => 'decimal:2',
            'is_sponsored' => 'boolean',
        ];
    }

    // ... votes(), comments(), creator(), category() unchanged ...

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function sponsoredActive(): \Illuminate\Database\Eloquent\Collection
    {
        return static::query()
            ->active()
            ->where('is_sponsored', true)
            ->orderBy('closes_at')
            ->limit(10)
            ->get();
    }

    public function isOpenForVoting(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->closes_at !== null
            && $this->closes_at->isFuture();
    }

    public function compactPool(): string
    {
        $n = (float) $this->total_pool;

        if ($n < 1000) {
            return (string) (int) $n;
        }

        $k = $n / 1000;

        if (fmod($k, 1.0) === 0.0) {
            return ((int) $k).'k';
        }

        return number_format($k, 1, '.', '').'k';
    }
}
```

- [ ] **Step 5: Update `BattleFactory`**

Modify `database/factories/BattleFactory.php`. Remove `'is_featured' => false,` from `definition()`. Add `'is_sponsored' => false,` and `'sponsor_handle' => null,`. Add a `sponsored()` state.

```php
public function definition(): array
{
    $title = $this->faker->unique()->sentence(4);

    return [
        'slug' => Str::slug($title).'-'.Str::random(6),
        'title' => $title,
        'description' => $this->faker->paragraph(),
        'side_a_label' => $this->faker->words(2, true),
        'side_b_label' => $this->faker->words(2, true),
        'side_a_image' => null,
        'side_b_image' => null,
        'status' => Battle::STATUS_ACTIVE,
        'opens_at' => now()->subMinute(),
        'closes_at' => now()->addDay(),
        'winning_side' => null,
        'total_pool' => 0,
        'created_by_id' => null,
        'settled_at' => null,
        'category_id' => null,
        'is_sponsored' => false,
        'sponsor_handle' => null,
    ];
}

public function sponsored(string $handle = '@brand'): static
{
    return $this->state(fn () => [
        'is_sponsored' => true,
        'sponsor_handle' => $handle,
    ]);
}
```

- [ ] **Step 6: Update the admin form**

Modify `app/Filament/Admin/Resources/Battles/Schemas/BattleForm.php`. Replace the `Toggle::make('is_featured')` block (and its helper text) with two new controls:

```php
Toggle::make('is_sponsored')
    ->label('Спонсорский слайд на главной')
    ->live()
    ->helperText('Отмеченные баттлы попадают в слайдер на главной странице.'),
TextInput::make('sponsor_handle')
    ->label('Хендл спонсора')
    ->prefix('@')
    ->placeholder('brand')
    ->visible(fn (callable $get) => (bool) $get('is_sponsored')),
```

Leave everything else in the file unchanged.

- [ ] **Step 7: Delete the obsolete `FeaturedBattleTest`**

```
rm tests/Feature/FeaturedBattleTest.php
```

- [ ] **Step 8: Update `BattleScopesTest`**

Modify `tests/Feature/BattleScopesTest.php`. Remove `test_featured_scope_returns_only_flagged_battles()` entirely. Final file:

```php
<?php

namespace Tests\Feature;

use App\Models\Battle;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattleScopesTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_scope_returns_only_active_battles(): void
    {
        Battle::query()->delete();

        Battle::factory()->create(['status' => Battle::STATUS_ACTIVE]);
        Battle::factory()->draft()->create();
        Battle::factory()->settled()->create();

        $this->assertSame(1, Battle::query()->active()->count());
    }

    public function test_category_relation(): void
    {
        $cat = Category::factory()->create();
        $battle = Battle::factory()->create(['category_id' => $cat->id]);

        $this->assertTrue($battle->category->is($cat));
    }
}
```

- [ ] **Step 9: Run the new tests — should pass**

```
vendor/bin/pest tests/Feature/Battle/SponsorshipTest.php tests/Feature/BattleScopesTest.php
```

Expected: all green.

- [ ] **Step 10: Run the full suite — check for fallout**

```
make test
```

Expected: there will be failures in `tests/Feature/Livewire/BattleIndexTest.php` (it references `selectCategory`, `is_featured`). Leave them for now — Task 6 replaces that test file entirely. All other tests should pass.

- [ ] **Step 11: Commit**

```
git add \
  database/migrations/2026_04_23_100000_add_sponsorship_to_battles_table.php \
  tests/Feature/Battle/SponsorshipTest.php \
  app/Models/Battle.php \
  database/factories/BattleFactory.php \
  app/Filament/Admin/Resources/Battles/Schemas/BattleForm.php \
  tests/Feature/BattleScopesTest.php
git rm tests/Feature/FeaturedBattleTest.php
git commit -m "Replace is_featured with is_sponsored / sponsor_handle"
```

---

## Task 2: Carousel component (Alpine + Blade)

**Files:**
- Modify: `resources/js/app.js`
- Create: `resources/views/components/carousel.blade.php`

This task has no automated tests (pure JS/Alpine behaviour). Manual verification comes in Task 7 when the home view consumes it.

- [ ] **Step 1: Register the Alpine data component**

Modify `resources/js/app.js`. Inside the existing `document.addEventListener('alpine:init', ...)` block (after the `voteWidget` registration), add a `carousel` registration:

```js
    window.Alpine.data('carousel', ({
        perPageMobile = 2,
        perPageDesktop = 4,
        autoAdvance = false,
        intervalMs = 6000,
        total = 0,
    } = {}) => ({
        perPage: perPageMobile,
        page: 0,
        total: Number(total) || 0,
        autoAdvance: !!autoAdvance,
        intervalMs: Number(intervalMs) || 6000,
        timer: null,
        touchStartX: null,
        mql: null,

        init() {
            this.mql = window.matchMedia('(min-width: 1024px)');
            this.perPage = this.mql.matches ? perPageDesktop : perPageMobile;
            this.mql.addEventListener('change', (e) => {
                this.perPage = e.matches ? perPageDesktop : perPageMobile;
                this.page = Math.min(this.page, this.pageCount - 1);
            });
            if (this.autoAdvance && this.pageCount > 1) {
                this.startTimer();
            }
        },
        destroy() {
            this.stopTimer();
        },
        get pageCount() {
            return Math.max(1, Math.ceil(this.total / this.perPage));
        },
        get isFirst() {
            return this.page <= 0;
        },
        get isLast() {
            return this.page >= this.pageCount - 1;
        },
        get trackStyle() {
            return `transform: translateX(-${this.page * 100}%);`;
        },
        get slideStyle() {
            return `flex: 0 0 ${100 / this.perPage}%; max-width: ${100 / this.perPage}%;`;
        },
        next() {
            if (this.pageCount <= 1) return;
            this.page = (this.page + 1) % this.pageCount;
        },
        prev() {
            if (this.pageCount <= 1) return;
            this.page = (this.page - 1 + this.pageCount) % this.pageCount;
        },
        goTo(page) {
            this.page = Math.max(0, Math.min(page, this.pageCount - 1));
        },
        startTimer() {
            this.stopTimer();
            this.timer = setInterval(() => this.next(), this.intervalMs);
        },
        stopTimer() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
        },
        onPause() {
            if (this.autoAdvance) this.stopTimer();
        },
        onResume() {
            if (this.autoAdvance && this.pageCount > 1) this.startTimer();
        },
        onTouchStart(e) {
            this.touchStartX = e.touches?.[0]?.clientX ?? null;
            this.onPause();
        },
        onTouchEnd(e) {
            if (this.touchStartX === null) return;
            const endX = e.changedTouches?.[0]?.clientX ?? this.touchStartX;
            const dx = endX - this.touchStartX;
            if (Math.abs(dx) > 50) {
                if (dx < 0) this.next(); else this.prev();
            }
            this.touchStartX = null;
            this.onResume();
        },
    }));
```

- [ ] **Step 2: Create the Blade component**

Create `resources/views/components/carousel.blade.php`:

```blade
@props([
    'perPageMobile' => 2,
    'perPageDesktop' => 4,
    'autoAdvance' => false,
    'intervalMs' => 6000,
    'showArrows' => true,
    'showDots' => true,
])

@php
    $slides = $slot->toHtml();
    // Count top-level children by splitting on root tags — simpler: trust caller passes one element per slide.
    // We pass `total` via JS by counting rendered slides at init time is fragile; use a data attribute.
    $total = 0;
    // Count opening tags at depth 1 is non-trivial in Blade — instead, let the caller specify, or count <li>/<div>/<a>:
    // Default approach: count direct non-empty children by a simple heuristic — occurrences of "\n    <" at the start.
    // More robust: rely on a <template>-style render, not computing total in PHP.
@endphp

<div
    {{ $attributes->merge(['class' => 'relative']) }}
    x-data="carousel({
        perPageMobile: {{ (int) $perPageMobile }},
        perPageDesktop: {{ (int) $perPageDesktop }},
        autoAdvance: {{ $autoAdvance ? 'true' : 'false' }},
        intervalMs: {{ (int) $intervalMs }},
        total: $refs.track?.children.length ?? 0,
    })"
    x-init="total = $refs.track.children.length; init()"
    x-on:mouseenter="onPause()"
    x-on:mouseleave="onResume()"
    x-on:focusin="onPause()"
    x-on:focusout="onResume()"
    x-on:touchstart.passive="onTouchStart($event)"
    x-on:touchend.passive="onTouchEnd($event)"
>
    <div class="overflow-hidden">
        <div class="flex transition-transform duration-300 ease-out" x-ref="track" :style="trackStyle">
            @foreach ($slot->toHtml() ? [$slot] : [] as $s)
                {{-- fallthrough: render slot children directly --}}
            @endforeach
            {{ $slot }}
        </div>
    </div>

    @if ($showArrows)
        <button type="button"
                x-show="pageCount > 1"
                x-on:click="prev()"
                :disabled="isFirst"
                class="absolute left-1 top-1/2 -translate-y-1/2 z-10 h-8 w-8 rounded-full bg-navy-900/70 text-white/80 hover:text-white border border-white/10 disabled:opacity-30 disabled:cursor-not-allowed flex items-center justify-center"
                aria-label="Previous">
            ‹
        </button>
        <button type="button"
                x-show="pageCount > 1"
                x-on:click="next()"
                :disabled="isLast"
                class="absolute right-1 top-1/2 -translate-y-1/2 z-10 h-8 w-8 rounded-full bg-navy-900/70 text-white/80 hover:text-white border border-white/10 disabled:opacity-30 disabled:cursor-not-allowed flex items-center justify-center"
                aria-label="Next">
            ›
        </button>
    @endif

    @if ($showDots)
        <div x-show="pageCount > 1" class="mt-3 flex justify-center gap-1.5">
            <template x-for="i in pageCount" :key="i">
                <button type="button"
                        x-on:click="goTo(i - 1)"
                        :class="page === (i - 1) ? 'bg-white' : 'bg-white/25 hover:bg-white/50'"
                        class="h-1.5 w-1.5 rounded-full transition-colors"
                        :aria-label="'Go to page ' + i"></button>
            </template>
        </div>
    @endif
</div>

<style>
    [x-data^="carousel"] [x-ref="track"] > * {
        flex: 0 0 calc(100% / var(--carousel-per-page, 2));
        max-width: calc(100% / var(--carousel-per-page, 2));
    }
</style>
```

Note: the component uses `$refs.track.children.length` at init time to learn the number of slides — each direct child of the `track` div is one slide. Callers must render each slide as a single top-level element inside the `<x-carousel>` slot. Per-slide width is driven by `slideStyle` computed from `perPage` — rather than relying on the inline `<style>` block, we apply widths via a child-selector binding below.

Replace the inline `<style>` block and `$slideStyle`-usage with per-child inline styles set at init. Rewrite the Blade so slides get their `style` applied via an Alpine helper. Simpler approach: make each child a wrapper that Alpine can target:

**Simpler revised Blade** — overwrite the prior file content with this cleaner version:

```blade
@props([
    'perPageMobile' => 2,
    'perPageDesktop' => 4,
    'autoAdvance' => false,
    'intervalMs' => 6000,
    'showArrows' => true,
    'showDots' => true,
])

<div
    {{ $attributes->merge(['class' => 'relative']) }}
    x-data="carousel({
        perPageMobile: {{ (int) $perPageMobile }},
        perPageDesktop: {{ (int) $perPageDesktop }},
        autoAdvance: {{ $autoAdvance ? 'true' : 'false' }},
        intervalMs: {{ (int) $intervalMs }},
    })"
    x-init="
        total = $refs.track.children.length;
        Array.from($refs.track.children).forEach((el) => { el.style.flex = '0 0 auto'; });
        $watch('perPage', (p) => {
            Array.from($refs.track.children).forEach((el) => { el.style.width = (100 / p) + '%'; });
        });
        Array.from($refs.track.children).forEach((el) => { el.style.width = (100 / perPage) + '%'; });
        init();
    "
    x-on:mouseenter="onPause()"
    x-on:mouseleave="onResume()"
    x-on:focusin="onPause()"
    x-on:focusout="onResume()"
    x-on:touchstart.passive="onTouchStart($event)"
    x-on:touchend.passive="onTouchEnd($event)"
>
    <div class="overflow-hidden">
        <div class="flex transition-transform duration-300 ease-out" x-ref="track" :style="trackStyle">
            {{ $slot }}
        </div>
    </div>

    @if ($showArrows)
        <button type="button"
                x-show="pageCount > 1"
                x-on:click="prev()"
                :disabled="isFirst"
                class="absolute left-1 top-1/2 -translate-y-1/2 z-10 h-8 w-8 rounded-full bg-navy-900/70 text-white/80 hover:text-white border border-white/10 disabled:opacity-30 disabled:cursor-not-allowed flex items-center justify-center"
                aria-label="Previous">‹</button>
        <button type="button"
                x-show="pageCount > 1"
                x-on:click="next()"
                :disabled="isLast"
                class="absolute right-1 top-1/2 -translate-y-1/2 z-10 h-8 w-8 rounded-full bg-navy-900/70 text-white/80 hover:text-white border border-white/10 disabled:opacity-30 disabled:cursor-not-allowed flex items-center justify-center"
                aria-label="Next">›</button>
    @endif

    @if ($showDots)
        <div x-show="pageCount > 1" class="mt-3 flex justify-center gap-1.5">
            <template x-for="i in pageCount" :key="i">
                <button type="button"
                        x-on:click="goTo(i - 1)"
                        :class="page === (i - 1) ? 'bg-white' : 'bg-white/25 hover:bg-white/50'"
                        class="h-1.5 w-1.5 rounded-full transition-colors"
                        :aria-label="'Go to page ' + i"></button>
            </template>
        </div>
    @endif
</div>
```

Overwrite the file with this cleaner version (delete the first, use only this).

- [ ] **Step 3: Ensure Vite rebuilds**

The dev server picks up changes to `resources/js/app.js` and `resources/views` automatically. Nothing to run explicitly. If the environment is not running Vite in watch mode: `make npm CMD="run dev"` in a separate shell.

- [ ] **Step 4: Commit**

```
git add resources/js/app.js resources/views/components/carousel.blade.php
git commit -m "Add reusable Alpine carousel component"
```

---

## Task 3: Icon components

**Files:**
- Create: `resources/views/components/icon/image-placeholder.blade.php`
- Create: `resources/views/components/icon/plus.blade.php`
- Create: `resources/views/components/icon/feed.blade.php`

No tests; pure Blade/SVG. Single commit.

- [ ] **Step 1: Create `image-placeholder.blade.php`**

Create `resources/views/components/icon/image-placeholder.blade.php`:

```blade
<svg {{ $attributes->merge(['class' => 'h-6 w-6 text-white/30', 'fill' => 'none', 'stroke' => 'currentColor', 'viewBox' => '0 0 24 24']) }}
     stroke-width="1.5" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round"
          d="M3.75 5.25a1.5 1.5 0 0 1 1.5-1.5h13.5a1.5 1.5 0 0 1 1.5 1.5v13.5a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5V5.25Z" />
    <path stroke-linecap="round" stroke-linejoin="round"
          d="m3.75 16.5 4.5-4.5 3 3 4.5-4.5 4.5 4.5M9 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
</svg>
```

- [ ] **Step 2: Create `plus.blade.php`**

Create `resources/views/components/icon/plus.blade.php`:

```blade
<svg {{ $attributes->merge(['class' => 'h-6 w-6', 'fill' => 'none', 'stroke' => 'currentColor', 'viewBox' => '0 0 24 24']) }}
     stroke-width="2" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
</svg>
```

- [ ] **Step 3: Create `feed.blade.php`**

Create `resources/views/components/icon/feed.blade.php`:

```blade
<svg {{ $attributes->merge(['class' => 'h-5 w-5', 'fill' => 'none', 'stroke' => 'currentColor', 'viewBox' => '0 0 24 24']) }}
     stroke-width="1.5" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round"
          d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 0 1-2.25 2.25M16.5 7.5V18a2.25 2.25 0 0 0 2.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 0 0 2.25 2.25h13.5M6 7.5h3v3H6v-3Z" />
</svg>
```

- [ ] **Step 4: Commit**

```
git add resources/views/components/icon/image-placeholder.blade.php \
        resources/views/components/icon/plus.blade.php \
        resources/views/components/icon/feed.blade.php
git commit -m "Add image-placeholder / plus / feed icons"
```

---

## Task 4: `<x-battle-tile>` component

**Files:**
- Create: `resources/views/components/battle-tile.blade.php`

No tests (pure Blade). Visuals verified in Task 7 (full home view).

- [ ] **Step 1: Create the file**

Create `resources/views/components/battle-tile.blade.php`:

```blade
@props([
    'battle',
])

@php
    /** @var \App\Models\Battle $battle */
    $timeLeft = $battle->closes_at
        ? $battle->closes_at->diff(now())
        : null;
    $timeLabel = $timeLeft
        ? sprintf('%02d:%02d', (int) $timeLeft->format('%a') * 24 + (int) $timeLeft->h, $timeLeft->i)
        : '—';
@endphp

<a href="{{ route('battles.show', $battle) }}"
   class="block rounded-xl border border-white/5 bg-white/[0.035] p-3 hover:bg-white/[0.06] transition">
    <div class="relative flex items-stretch gap-2">
        <div class="flex-1 aspect-square rounded-lg bg-navy-800 overflow-hidden flex items-center justify-center">
            @if ($battle->side_a_image)
                <img src="{{ $battle->side_a_image }}" alt="{{ $battle->side_a_label }}" class="h-full w-full object-cover">
            @else
                <x-icon.image-placeholder />
            @endif
        </div>
        <div class="flex-1 aspect-square rounded-lg bg-navy-800 overflow-hidden flex items-center justify-center">
            @if ($battle->side_b_image)
                <img src="{{ $battle->side_b_image }}" alt="{{ $battle->side_b_label }}" class="h-full w-full object-cover">
            @else
                <x-icon.image-placeholder />
            @endif
        </div>
        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
                     h-8 w-8 rounded-full border border-white/20 bg-navy-900
                     text-[10px] font-bold text-white/90 flex items-center justify-center">
            VS
        </span>
    </div>

    <div class="mt-2 text-sm text-white/90 truncate">{{ $battle->title }}</div>

    <div class="mt-1 flex items-center justify-between text-[11px] text-white/55">
        <span>💰 {{ $battle->compactPool() }}</span>
        <span>⏱ {{ $timeLabel }}</span>
    </div>
</a>
```

- [ ] **Step 2: Commit**

```
git add resources/views/components/battle-tile.blade.php
git commit -m "Add battle-tile component for rails"
```

---

## Task 5: `<x-battle-featured-card>` component

**Files:**
- Create: `resources/views/components/battle-featured-card.blade.php`

- [ ] **Step 1: Create the file**

Create `resources/views/components/battle-featured-card.blade.php`:

```blade
@props([
    'battle',
])

@php
    /** @var \App\Models\Battle $battle */
    $closesIso = optional($battle->closes_at)->toIso8601String();
@endphp

<article class="mx-3 rounded-2xl border border-white/5 bg-white/[0.04] p-4 sm:p-5">
    <div class="relative flex items-stretch gap-3">
        <figure class="flex-1 flex flex-col items-center gap-2">
            <div class="w-full aspect-square rounded-xl bg-navy-800 overflow-hidden flex items-center justify-center">
                @if ($battle->side_a_image)
                    <img src="{{ $battle->side_a_image }}" alt="{{ $battle->side_a_label }}" class="h-full w-full object-cover">
                @else
                    <x-icon.image-placeholder class="h-10 w-10 text-white/30" />
                @endif
            </div>
            <figcaption class="font-semibold text-white">{{ $battle->side_a_label }}</figcaption>
        </figure>

        <figure class="flex-1 flex flex-col items-center gap-2">
            <div class="w-full aspect-square rounded-xl bg-navy-800 overflow-hidden flex items-center justify-center">
                @if ($battle->side_b_image)
                    <img src="{{ $battle->side_b_image }}" alt="{{ $battle->side_b_label }}" class="h-full w-full object-cover">
                @else
                    <x-icon.image-placeholder class="h-10 w-10 text-white/30" />
                @endif
            </div>
            <figcaption class="font-semibold text-white">{{ $battle->side_b_label }}</figcaption>
        </figure>

        <span class="absolute top-[30%] left-1/2 -translate-x-1/2 -translate-y-1/2
                     h-10 w-10 rounded-full border border-white/25 bg-navy-900
                     text-xs font-bold text-white/90 flex items-center justify-center">
            VS
        </span>
    </div>

    <div class="mt-4 flex items-center justify-center gap-6 text-sm text-white/70">
        <span>💰 {{ number_format((float) $battle->total_pool, 0, '.', ',') }} VRS</span>
        @if ($closesIso)
            <span class="font-mono" x-data="countdown('{{ $closesIso }}')" x-init="start()" x-text="label">—</span>
        @endif
    </div>

    <div class="mt-4 grid grid-cols-2 gap-3">
        <a href="{{ route('battles.show', $battle) }}"
           class="rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 transition py-3 text-center text-sm font-semibold text-white">
            {{ __('battle.vote_for_side', ['side' => $battle->side_a_label]) }}
        </a>
        <a href="{{ route('battles.show', $battle) }}"
           class="rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 transition py-3 text-center text-sm font-semibold text-white">
            {{ __('battle.vote_for_side', ['side' => $battle->side_b_label]) }}
        </a>
    </div>

    @if ($battle->is_sponsored && $battle->sponsor_handle)
        <div class="mt-3 text-center text-xs text-white/45 tracking-wide">
            {{ __('battle.sponsored_by', ['handle' => $battle->sponsor_handle]) }}
        </div>
    @endif
</article>
```

- [ ] **Step 2: Add i18n keys**

Modify `lang/en/battle.php`, adding inside the returned array (order doesn't matter):

```php
    'vote_for_side' => 'VOTE :side',
    'sponsored_by' => 'Sponsored by :handle',
    'no_active_in_category' => 'No active battles in this category yet.',
```

Modify `lang/ru/battle.php`:

```php
    'vote_for_side' => 'ЗА :side',
    'sponsored_by' => 'Спонсор: :handle',
    'no_active_in_category' => 'В этой категории пока нет активных баттлов.',
```

- [ ] **Step 3: Commit**

```
git add resources/views/components/battle-featured-card.blade.php \
        lang/en/battle.php lang/ru/battle.php
git commit -m "Add battle-featured-card component + i18n"
```

---

## Task 6: Rewrite `BattleIndex` Livewire + home view

**Files:**
- Modify: `app/Livewire/BattleIndex.php`
- Create: `tests/Feature/Home/HomePageSectionsTest.php`
- Delete: `tests/Feature/Livewire/BattleIndexTest.php`
- Modify: `resources/views/livewire/battle-index.blade.php`
- Delete: `resources/views/livewire/battle-index/featured-card.blade.php`
- Delete: `resources/views/livewire/battle-index/hot-rail.blade.php`
- Delete: `resources/views/livewire/battle-index/category-chips.blade.php`
- Delete: `resources/views/livewire/battle-index/all-list.blade.php`
- Delete: `resources/views/components/battle-row.blade.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Home/HomePageSectionsTest.php`:

```php
<?php

namespace Tests\Feature\Home;

use App\Livewire\BattleIndex;
use App\Models\Battle;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HomePageSectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_only_active_sponsored_battles_in_slider(): void
    {
        $active = Battle::factory()->sponsored()->create();
        Battle::factory()->settled()->create(['is_sponsored' => true]);
        Battle::factory()->create(['is_sponsored' => false]);

        Livewire::test(BattleIndex::class)
            ->assertViewHas('sponsored', fn ($s) => $s->count() === 1 && $s->first()->is($active));
    }

    public function test_hot_rail_is_ordered_by_total_pool_desc_and_limited_to_ten(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            Battle::factory()->create(['total_pool' => $i * 100]);
        }

        Livewire::test(BattleIndex::class)
            ->assertViewHas('hot', fn ($hot) => $hot->count() === 10
                && (int) $hot->first()->total_pool === 1200
                && (int) $hot->last()->total_pool === 300);
    }

    public function test_sponsored_battles_are_excluded_from_hot_rail(): void
    {
        $sponsored = Battle::factory()->sponsored()->create(['total_pool' => 9999]);
        Battle::factory()->create(['total_pool' => 100]);

        Livewire::test(BattleIndex::class)
            ->assertViewHas('hot', fn ($hot) => ! $hot->contains('id', $sponsored->id));
    }

    public function test_category_rails_are_ordered_by_sort_order(): void
    {
        $c1 = Category::factory()->create(['slug' => 'one', 'sort_order' => 10]);
        $c2 = Category::factory()->create(['slug' => 'two', 'sort_order' => 20]);
        Battle::factory()->create(['category_id' => $c1->id]);
        Battle::factory()->create(['category_id' => $c2->id]);

        Livewire::test(BattleIndex::class)
            ->assertViewHas('categoryRails', function ($rails) use ($c1, $c2) {
                $slugs = $rails->pluck('slug')->values()->all();
                return $slugs === ['one', 'two'];
            });
    }

    public function test_empty_category_rails_are_hidden(): void
    {
        $withBattles = Category::factory()->create(['sort_order' => 10]);
        Category::factory()->create(['sort_order' => 20]); // empty
        Battle::factory()->create(['category_id' => $withBattles->id]);

        Livewire::test(BattleIndex::class)
            ->assertViewHas('categoryRails', fn ($rails) => $rails->count() === 1
                && $rails->first()->is($withBattles));
    }

    public function test_sponsored_battles_are_excluded_from_category_rails(): void
    {
        $c = Category::factory()->create();
        $sponsored = Battle::factory()->sponsored()->create([
            'category_id' => $c->id,
            'total_pool' => 9999,
        ]);
        $regular = Battle::factory()->create([
            'category_id' => $c->id,
            'total_pool' => 50,
        ]);

        Livewire::test(BattleIndex::class)
            ->assertViewHas('categoryRails', function ($rails) use ($sponsored, $regular) {
                $rail = $rails->first();
                $ids = $rail->battles->pluck('id')->all();
                return in_array($regular->id, $ids, true)
                    && ! in_array($sponsored->id, $ids, true);
            });
    }
}
```

- [ ] **Step 2: Delete the obsolete test**

```
rm tests/Feature/Livewire/BattleIndexTest.php
```

- [ ] **Step 3: Run the new tests — should fail**

```
vendor/bin/pest tests/Feature/Home/HomePageSectionsTest.php
```

Expected: `categoryRails` not present; `sponsored` not present; `hot` shape wrong. The existing component is still the old one.

- [ ] **Step 4: Rewrite `BattleIndex`**

Overwrite `app/Livewire/BattleIndex.php`:

```php
<?php

namespace App\Livewire;

use App\Models\Battle;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

class BattleIndex extends Component
{
    #[Layout('layouts.app')]
    public function render(): View
    {
        $sponsored = Battle::sponsoredActive();
        $sponsoredIds = $sponsored->pluck('id')->all();

        $hot = Battle::query()
            ->active()
            ->with('category')
            ->when($sponsoredIds, fn ($q) => $q->whereNotIn('id', $sponsoredIds))
            ->orderByDesc('total_pool')
            ->limit(10)
            ->get();

        $categoryRails = Category::query()
            ->orderBy('sort_order')
            ->with(['battles' => fn ($q) => $q
                ->active()
                ->when($sponsoredIds, fn ($qq) => $qq->whereNotIn('id', $sponsoredIds))
                ->orderByDesc('total_pool')
                ->limit(10),
            ])
            ->get()
            ->filter(fn (Category $c) => $c->battles->isNotEmpty())
            ->values();

        return view('livewire.battle-index', [
            'sponsored' => $sponsored,
            'hot' => $hot,
            'categoryRails' => $categoryRails,
        ]);
    }
}
```

- [ ] **Step 5: Rewrite the view**

Overwrite `resources/views/livewire/battle-index.blade.php`:

```blade
<div class="pb-6 lg:grid lg:grid-cols-[minmax(0,1fr)_320px] lg:gap-8 lg:max-w-7xl lg:mx-auto lg:px-6">
    <div class="min-w-0 space-y-6">
        @if ($sponsored->isNotEmpty())
            <section>
                <x-carousel :per-page-mobile="1" :per-page-desktop="1" :auto-advance="true">
                    @foreach ($sponsored as $battle)
                        <div>
                            <x-battle-featured-card :battle="$battle" />
                        </div>
                    @endforeach
                </x-carousel>
            </section>
        @endif

        @if ($hot->isNotEmpty())
            <section>
                <header class="flex items-baseline justify-between px-3 mb-2">
                    <div class="text-[11px] uppercase tracking-wider text-white/55">
                        {{ __('battle.hot') }}
                    </div>
                </header>
                <div class="px-3">
                    <x-carousel>
                        @foreach ($hot as $battle)
                            <div class="pr-2">
                                <x-battle-tile :battle="$battle" />
                            </div>
                        @endforeach
                    </x-carousel>
                </div>
            </section>
        @endif

        @foreach ($categoryRails as $category)
            <section>
                <header class="flex items-baseline justify-between px-3 mb-2">
                    <div class="text-[11px] uppercase tracking-wider text-white/55">
                        {{ $category->localized_name }}
                    </div>
                    <a href="{{ route('categories.show', $category) }}"
                       class="text-[11px] text-glow-cyan hover:underline">
                        {{ __('battle.view_all') }} ›
                    </a>
                </header>
                <div class="px-3">
                    <x-carousel>
                        @foreach ($category->battles as $battle)
                            <div class="pr-2">
                                <x-battle-tile :battle="$battle" />
                            </div>
                        @endforeach
                    </x-carousel>
                </div>
            </section>
        @endforeach
    </div>

    <aside class="hidden lg:block">
        <livewire:sidebar-widgets />
    </aside>
</div>
```

Note: each slide must be wrapped in a single element (here `<div>`) so the carousel component can measure `children.length` correctly.

- [ ] **Step 6: Delete obsolete partials**

```
rm resources/views/livewire/battle-index/featured-card.blade.php
rm resources/views/livewire/battle-index/hot-rail.blade.php
rm resources/views/livewire/battle-index/category-chips.blade.php
rm resources/views/livewire/battle-index/all-list.blade.php
rm resources/views/components/battle-row.blade.php
rmdir resources/views/livewire/battle-index 2>/dev/null || true
```

- [ ] **Step 7: Clean up dead i18n keys**

Edit `lang/en/battle.php`. Remove these keys:

```php
'all_chip' => 'All',
'finished_chip' => 'Finished',
'all_battles' => 'All battles',
'no_battles' => 'No battles yet.',
'no_battles_in_category' => 'Nothing in this category yet.',
'no_settled_battles' => 'No settled battles yet.',
'featured' => 'Featured battle',
```

Edit `lang/ru/battle.php`. Remove these keys:

```php
'all_chip' => 'Все',
'finished_chip' => 'Завершённые',
'all_battles' => 'Все баттлы',
'no_battles' => 'Баттлов пока нет.',
'no_battles_in_category' => 'В этой категории пока пусто.',
'no_settled_battles' => 'Пока нет завершённых.',
'featured' => 'Баттл дня',
```

Keep `load_more`, `refunded_tie`, `view_all` (still used).

Before committing, grep to confirm none of the removed keys are referenced:

```
grep -rnE "battle\.(all_chip|finished_chip|all_battles|no_battles|no_battles_in_category|no_settled_battles|featured)" app resources tests
```

Expected: no matches.

- [ ] **Step 8: Run tests**

```
vendor/bin/pest tests/Feature/Home/HomePageSectionsTest.php
vendor/bin/pest
```

Expected: all green.

- [ ] **Step 9: Commit**

```
git add app/Livewire/BattleIndex.php \
        resources/views/livewire/battle-index.blade.php \
        tests/Feature/Home/HomePageSectionsTest.php \
        lang/en/battle.php lang/ru/battle.php
git rm tests/Feature/Livewire/BattleIndexTest.php \
       resources/views/livewire/battle-index/featured-card.blade.php \
       resources/views/livewire/battle-index/hot-rail.blade.php \
       resources/views/livewire/battle-index/category-chips.blade.php \
       resources/views/livewire/battle-index/all-list.blade.php \
       resources/views/components/battle-row.blade.php
git commit -m "Rewrite home: sponsored slider + HOT rail + category rails"
```

---

## Task 7: `CategoryShow` page

**Files:**
- Create: `app/Livewire/CategoryShow.php`
- Create: `resources/views/livewire/category-show.blade.php`
- Create: `tests/Feature/Categories/CategoryShowTest.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Categories/CategoryShowTest.php`:

```php
<?php

namespace Tests\Feature\Categories;

use App\Models\Battle;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_active_battles_for_the_category(): void
    {
        $cat = Category::factory()->create(['slug' => 'sports']);
        $active = Battle::factory()->create(['category_id' => $cat->id]);
        Battle::factory()->settled()->create(['category_id' => $cat->id]);

        $this->get(route('categories.show', $cat))
            ->assertOk()
            ->assertSee($active->title);
    }

    public function test_shows_empty_state_when_no_active_battles(): void
    {
        $cat = Category::factory()->create(['slug' => 'memes']);
        Battle::factory()->settled()->create(['category_id' => $cat->id]);

        $this->get(route('categories.show', $cat))
            ->assertOk()
            ->assertSee(__('battle.no_active_in_category'));
    }

    public function test_returns_404_for_unknown_slug(): void
    {
        $this->get('/categories/does-not-exist')->assertNotFound();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```
vendor/bin/pest tests/Feature/Categories/CategoryShowTest.php
```

Expected: route `categories.show` not defined.

- [ ] **Step 3: Create the Livewire component**

Create `app/Livewire/CategoryShow.php`:

```php
<?php

namespace App\Livewire;

use App\Models\Battle;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class CategoryShow extends Component
{
    use WithPagination;

    public Category $category;

    public function mount(Category $category): void
    {
        $this->category = $category;
    }

    #[Layout('layouts.app')]
    public function render(): View
    {
        $battles = Battle::query()
            ->active()
            ->where('category_id', $this->category->id)
            ->with('category')
            ->orderByDesc('closes_at')
            ->paginate(20);

        return view('livewire.category-show', [
            'battles' => $battles,
        ]);
    }
}
```

- [ ] **Step 4: Create the view**

Create `resources/views/livewire/category-show.blade.php`:

```blade
<div class="pb-6 lg:grid lg:grid-cols-[minmax(0,1fr)_320px] lg:gap-8 lg:max-w-7xl lg:mx-auto lg:px-6">
    <div class="min-w-0">
        <header class="px-3 mb-4">
            <h1 class="text-xl font-semibold text-white">{{ $category->localized_name }}</h1>
            <p class="text-xs text-white/55 mt-1">
                {{ trans_choice(':count active battles', $battles->total(), ['count' => $battles->total()]) }}
            </p>
        </header>

        @if ($battles->total() === 0)
            <div class="mx-3 rounded-xl border border-dashed border-white/10 p-8 text-center text-sm text-white/55">
                {{ __('battle.no_active_in_category') }}
                <div class="mt-3">
                    <a href="{{ route('home') }}" class="text-glow-cyan hover:underline">← {{ __('nav.home') }}</a>
                </div>
            </div>
        @else
            <div class="grid grid-cols-2 gap-3 px-3 lg:grid-cols-4">
                @foreach ($battles as $battle)
                    <x-battle-tile :battle="$battle" />
                @endforeach
            </div>

            @if ($battles->hasMorePages())
                <div class="px-3 mt-4">
                    <button type="button" wire:click="nextPage"
                            class="w-full rounded-xl border border-dashed border-white/10 py-3 text-sm text-white/60 hover:text-white">
                        {{ __('battle.load_more') }}
                    </button>
                </div>
            @endif
        @endif
    </div>

    <aside class="hidden lg:block">
        <livewire:sidebar-widgets />
    </aside>
</div>
```

- [ ] **Step 5: Register the route**

Modify `routes/web.php`. Add after the existing `/battles/{battle:slug}` line:

```php
Route::get('/categories/{category:slug}', \App\Livewire\CategoryShow::class)->name('categories.show');
```

Full imports block of `routes/web.php` should start with:

```php
<?php

use App\Http\Controllers\ProfileController;
use App\Livewire\BattleIndex;
use App\Livewire\BattleShow;
use App\Livewire\CategoryShow;
use App\Livewire\Leaderboard;
use App\Livewire\MyBets;
use App\Livewire\ReferralPanel;
use Illuminate\Support\Facades\Route;
```

And the route line:

```php
Route::get('/categories/{category:slug}', CategoryShow::class)->name('categories.show');
```

- [ ] **Step 6: Run tests**

```
vendor/bin/pest tests/Feature/Categories/CategoryShowTest.php
```

Expected: all green.

- [ ] **Step 7: Commit**

```
git add app/Livewire/CategoryShow.php \
        resources/views/livewire/category-show.blade.php \
        tests/Feature/Categories/CategoryShowTest.php \
        routes/web.php
git commit -m "Add CategoryShow page (/categories/{slug})"
```

---

## Task 8: Bottom nav — 5 tabs with FAB

**Files:**
- Modify: `resources/views/layouts/bottom-nav.blade.php`
- Modify: `lang/en/nav.php`, `lang/ru/nav.php`
- Create: `tests/Feature/Navigation/BottomNavTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Navigation/BottomNavTest.php`:

```php
<?php

namespace Tests\Feature\Navigation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BottomNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_renders_five_bottom_nav_tabs(): void
    {
        $response = $this->get(route('home'))->assertOk();

        $response->assertSee(__('nav.home'), false);
        $response->assertSee(__('nav.feed'), false);
        $response->assertSee(__('nav.create'), false);
        $response->assertSee(__('nav.leaderboard'), false);
        $response->assertSee(__('nav.profile'), false);
    }

    public function test_feed_and_create_tabs_are_disabled_placeholders(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        // Both placeholders expose aria-disabled="true" so screen readers skip them.
        $this->assertStringContainsString('aria-disabled="true"', $html);
    }

    public function test_my_bets_tab_is_not_in_bottom_nav(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString(__('nav.my_bets'), $html);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```
vendor/bin/pest tests/Feature/Navigation/BottomNavTest.php
```

Expected: "feed", "create" translations fall back to keys, and `my_bets` text is present.

- [ ] **Step 3: Add i18n keys**

Modify `lang/en/nav.php`. Final content:

```php
<?php

return [
    'battles' => 'Battles',
    'referrals' => 'Referrals',
    'balance' => 'Balance',
    'login' => 'Sign in',
    'register' => 'Register',
    'profile' => 'Profile',
    'logout' => 'Sign out',
    'home' => 'Home',
    'leaderboard' => 'Leaderboard',
    'my_bets' => 'My Bets',
    'feed' => 'Feed',
    'create' => 'Create',
    'coming_soon' => 'Coming soon',
];
```

Modify `lang/ru/nav.php`. Final content:

```php
<?php

return [
    'battles' => 'Баттлы',
    'referrals' => 'Рефералы',
    'balance' => 'Баланс',
    'login' => 'Вход',
    'register' => 'Регистрация',
    'profile' => 'Профиль',
    'logout' => 'Выйти',
    'home' => 'Главная',
    'leaderboard' => 'Лидеры',
    'my_bets' => 'Мои ставки',
    'feed' => 'Лента',
    'create' => 'Создать',
    'coming_soon' => 'Скоро',
];
```

- [ ] **Step 4: Rewrite the bottom nav view**

Overwrite `resources/views/layouts/bottom-nav.blade.php`:

```blade
@php
    $tabs = [
        [
            'route' => 'home',
            'match' => ['home', 'battles.*', 'categories.*'],
            'label' => __('nav.home'),
            'icon' => 'home',
        ],
        [
            'route' => null,
            'match' => [],
            'label' => __('nav.feed'),
            'icon' => 'feed',
            'disabled' => true,
        ],
        [
            'route' => null,
            'match' => [],
            'label' => __('nav.create'),
            'icon' => 'plus',
            'disabled' => true,
            'fab' => true,
        ],
        [
            'route' => 'leaderboard',
            'match' => ['leaderboard'],
            'label' => __('nav.leaderboard'),
            'icon' => 'trophy',
        ],
        [
            'route' => 'profile.edit',
            'match' => ['profile.*'],
            'label' => __('nav.profile'),
            'icon' => 'user',
        ],
    ];
@endphp

<nav class="fixed bottom-0 inset-x-0 z-40 sm:hidden bg-navy-900/95 backdrop-blur border-t border-white/5 pt-5 pb-[env(safe-area-inset-bottom)]">
    <ul class="grid grid-cols-5 items-end">
        @foreach ($tabs as $tab)
            <li class="flex justify-center">
                @if (! empty($tab['fab']))
                    <button type="button" disabled aria-disabled="true"
                            title="{{ __('nav.coming_soon') }}"
                            class="-mt-7 h-14 w-14 rounded-full bg-gradient-to-br from-vote-blue-from to-vote-purple-to shadow-vote-blue text-white flex items-center justify-center cursor-not-allowed">
                        <x-dynamic-component :component="'icon.'.$tab['icon']" class="h-6 w-6" />
                        <span class="sr-only">{{ $tab['label'] }}</span>
                    </button>
                @elseif (! empty($tab['disabled']))
                    <button type="button" disabled aria-disabled="true"
                            title="{{ __('nav.coming_soon') }}"
                            class="flex flex-col items-center gap-1 py-2.5 text-[10px] text-white/35 cursor-not-allowed">
                        <x-dynamic-component :component="'icon.'.$tab['icon']" class="h-5 w-5" />
                        <span>{{ $tab['label'] }}</span>
                    </button>
                @elseif ($tab['route'] && Route::has($tab['route']))
                    <a href="{{ route($tab['route']) }}"
                       class="flex flex-col items-center gap-1 py-2.5 text-[10px] {{ request()->routeIs(...$tab['match']) ? 'text-white' : 'text-white/55 hover:text-white' }}">
                        <x-dynamic-component :component="'icon.'.$tab['icon']" class="h-5 w-5" />
                        <span>{{ $tab['label'] }}</span>
                    </a>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
```

- [ ] **Step 5: Run tests**

```
vendor/bin/pest tests/Feature/Navigation/BottomNavTest.php
vendor/bin/pest
```

Expected: green.

- [ ] **Step 6: Commit**

```
git add resources/views/layouts/bottom-nav.blade.php \
        lang/en/nav.php lang/ru/nav.php \
        tests/Feature/Navigation/BottomNavTest.php
git commit -m "5-tab bottom nav with Create FAB and Feed placeholder"
```

---

## Task 9: Admin sponsorship form — dedicated test

**Files:**
- Create: `tests/Feature/Admin/BattleSponsorshipTest.php`

The admin form was already modified in Task 1; this task just adds a smoke test for the new Filament fields.

- [ ] **Step 1: Write the test**

Create `tests/Feature/Admin/BattleSponsorshipTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Resources\Battles\Schemas\BattleForm;
use App\Models\Battle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattleSponsorshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_battle_form_schema_includes_sponsorship_controls(): void
    {
        $names = [];

        // Use reflection to walk the schema components without booting the full Filament panel.
        $schema = new \Filament\Schemas\Schema(new \Filament\Forms\Form());
        BattleForm::configure($schema);

        foreach ($schema->getComponents() as $component) {
            if (method_exists($component, 'getName')) {
                $names[] = $component->getName();
            }
        }

        $this->assertContains('is_sponsored', $names);
        $this->assertContains('sponsor_handle', $names);
        $this->assertNotContains('is_featured', $names);
    }

    public function test_battle_can_persist_sponsorship_fields(): void
    {
        $battle = Battle::factory()->create([
            'is_sponsored' => true,
            'sponsor_handle' => '@acme',
        ]);

        $fresh = $battle->fresh();
        $this->assertTrue($fresh->is_sponsored);
        $this->assertSame('@acme', $fresh->sponsor_handle);
    }
}
```

- [ ] **Step 2: Run test**

```
vendor/bin/pest tests/Feature/Admin/BattleSponsorshipTest.php
```

If reflecting into `Filament\Schemas\Schema` fails in the test environment, **fall back to persistence-only checks**: keep `test_battle_can_persist_sponsorship_fields` and replace the first test with a file-content assertion:

```php
public function test_battle_form_file_declares_sponsorship_fields(): void
{
    $source = file_get_contents(base_path('app/Filament/Admin/Resources/Battles/Schemas/BattleForm.php'));

    $this->assertStringContainsString("Toggle::make('is_sponsored')", $source);
    $this->assertStringContainsString("TextInput::make('sponsor_handle')", $source);
    $this->assertStringNotContainsString("Toggle::make('is_featured')", $source);
}
```

Use whichever version runs cleanly. Prefer the reflection version if it works; otherwise commit with the file-content version.

- [ ] **Step 3: Commit**

```
git add tests/Feature/Admin/BattleSponsorshipTest.php
git commit -m "Smoke test sponsorship fields in BattleForm"
```

---

## Task 10: Full verification gate

- [ ] **Step 1: Format**

```
make pint
```

Expected: no changes or a tiny diff. If Pint reformatted files, stage and amend (or recommit) in a separate `style: Pint auto-format` commit.

- [ ] **Step 2: Static analysis**

```
make stan
```

Expected: 0 errors. If Larastan flags something in the new code, fix it (do not extend the baseline without reason).

Remember: the codebase requires a higher PHP memory limit for PHPStan. If it OOMs, run inside the workspace with:

```
make ws
php -d memory_limit=512M ./vendor/bin/phpstan analyse
```

- [ ] **Step 3: Full test suite**

```
make test
```

Expected: all green.

- [ ] **Step 4: Manual verification on mobile & desktop viewports**

Open `http://versus.local/` after `make up`:

- Mobile viewport (≤640px): verify sponsored slider (messi-vs-ronaldo should be sponsored after the migration), HOT rail with 2 tiles visible, each category rail shows 2 tiles with a working "View all" link.
- Desktop viewport (≥1024px): same sections, 4 tiles per page, sidebar widgets visible on the right.
- Carousel: swipe (mobile), arrow buttons (both), dot clicks, auto-advance on the sponsored slider (~6s), pause on hover.
- Click a tile — lands on `battles.show`.
- Click "View all" — lands on `/categories/{slug}` with the full list.
- Bottom nav: 5 tabs on mobile, Feed/Create visually disabled (`cursor-not-allowed`, dimmed), tooltip "Coming soon" on hover.
- Admin: log in at `/admin`, edit a battle, toggle "Спонсорский слайд на главной", fill handle, save; confirm it appears in the home slider.
- `/my-bets` still loads via direct URL.

- [ ] **Step 5: If everything is green, push**

Confirm with the user before pushing. No further commits needed — each task committed its own changes.

---

## Self-review

**1. Spec coverage**

- Sponsorship fields + `Battle::sponsoredActive()` → Task 1 ✓
- `Battle::compactPool()` → Task 1 ✓
- `Category::battles()` relation → already exists (no task) ✓
- Carousel component → Task 2 ✓
- Battle tile + featured card components → Tasks 4, 5 ✓
- Icon components (image-placeholder, plus, feed) → Task 3 ✓
- `BattleIndex` rewrite + view → Task 6 ✓
- Remove obsolete partials / `battle-row` → Task 6 ✓
- `CategoryShow` page + route → Task 7 ✓
- Bottom nav 5-tabs + FAB + `aria-disabled` → Task 8 ✓
- Admin `BattleForm` sponsorship fields → Task 1 (+ smoke test in Task 9) ✓
- i18n new keys (nav.feed, nav.create, nav.coming_soon, battle.sponsored_by, battle.no_active_in_category, battle.vote_for_side) → Tasks 5, 6, 8 ✓
- i18n dead keys removal (`all_chip`, `finished_chip`, `all_battles`, `no_battles`, `no_battles_in_category`, `no_settled_battles`, `featured`) → Task 6 Step 7 ✓
- Tests: `HomePageSectionsTest`, `CategoryShowTest`, `BottomNavTest`, `BattleSponsorshipTest`, `SponsorshipTest` → Tasks 1, 6, 7, 8, 9 ✓
- Removal of old `FeaturedBattleTest`, old `BattleIndexTest`, `scopeFeatured`, `resolveFeatured` → Task 1 (tests + model), Task 6 (BattleIndexTest) ✓
- Final verification gate → Task 10 ✓

**2. Placeholder scan**

No `TBD`/`TODO`/`fill in later` in any task. Every code block is complete. The admin reflection test in Task 9 includes an explicit fallback for the realistic case that reflection into Filament internals fails in a unit context — not a placeholder, an alternative path.

**3. Type & name consistency**

- `Battle::sponsoredActive()` — defined in Task 1, consumed in Task 6.
- `Battle::compactPool()` — defined in Task 1, consumed in Task 4 (`<x-battle-tile>`).
- `Category::battles` — already in the repo.
- `<x-carousel>` props: `per-page-mobile` / `per-page-desktop` / `auto-advance` / `interval-ms` — defined in Task 2, consumed in Task 6.
- `<x-battle-tile :battle="...">` — defined in Task 4, used in Tasks 6 and 7.
- `<x-battle-featured-card :battle="...">` — defined in Task 5, used in Task 6.
- Route name `categories.show` — defined in Task 7, used in Task 6 view.
- i18n keys: `battle.vote_for_side`, `battle.sponsored_by`, `battle.no_active_in_category`, `nav.feed`, `nav.create`, `nav.coming_soon` — all declared in Task 5/6/8, referenced consistently.
- Route patterns in bottom nav include `categories.*` so the Home tab stays highlighted on the category page.

No inconsistencies found.

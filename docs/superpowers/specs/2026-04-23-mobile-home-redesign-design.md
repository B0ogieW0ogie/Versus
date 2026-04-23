# Mobile home redesign — design spec

**Date:** 2026-04-23
**Status:** Draft, pending implementation plan
**Scope:** Redesign the `BattleIndex` (home) page around a mockup with a sponsored slider, per-category rails, and a 5-tab bottom nav with a central FAB. Dark theme retained. Mobile-first; desktop keeps the 2-column layout with sidebar but uses the same components with wider grid.

## Goals

- Bring the mobile home page visually in line with the provided mockup (sponsored featured slider + per-category rails of 2 tiles).
- Replace the current category-chips filter with dedicated per-category sections, each linking to a full-list category page via "View all".
- Introduce real sponsorship as a first-class backend concept (`is_sponsored`, `sponsor_handle` on `battles`).
- Swap the 4-tab bottom nav for a 5-tab layout with a central Create FAB (Feed and Create are "Coming soon" placeholders).
- Share one carousel component across the sponsored slider, the HOT rail, and every category rail.

## Non-goals

- No changes to the topbar (`layouts/navigation.blade.php`).
- No changes to existing money/vote flow, `BattleShow`, `BattleVoteWidget`, `MyBets`, or admin outside of adding two fields to the Battle form.
- No theme switch (stays dark).
- No redesign of the Profile page. `MyBets` stays reachable at `/my-bets` via URL only. The "move MyBets into Profile" work is a separate future task.
- No Finished/settled listing on home or as a chip. Settled battles remain accessible only via their own URLs and (future) profile history.
- No `Sponsorship` model with budgets/windows — that's explicitly out of scope for this iteration.

## Architecture overview

Home page composition (top to bottom):

1. **Topbar** — unchanged.
2. **Sponsored slider** — Alpine carousel (auto-advance) over `Battle::sponsoredActive()` (up to 10). Renders `<x-battle-featured-card>` per slide. Hidden if zero sponsored active battles.
3. **HOT rail** — Alpine carousel (no auto-advance), up to 10 active battles ordered by `total_pool desc`, excluding ones already shown in the sponsored slider. Renders `<x-battle-tile>`. Heading "HOT BATTLES" + "View all" (clears filters / scrolls to HOT category — see note below).
4. **Category rails** — one rail per non-empty `Category` (ordered by `sort_order`). Each rail is an Alpine carousel over up to 10 active battles in that category (ordered by `total_pool desc`), excluding sponsored. "View all" links to `/categories/{slug}`. Rails for empty categories are not rendered.
5. **Bottom nav** — mobile-only (`sm:hidden`), 5 tabs with central FAB, unchanged on desktop.

Desktop layout preserves the existing `lg:grid lg:grid-cols-[minmax(0,1fr)_320px]` wrapper with the right-side `<livewire:sidebar-widgets />`. Carousels show 4 tiles per page on desktop vs 2 on mobile.

"View all" on HOT: since HOT is not a category, "View all" on the HOT rail is either (a) omitted, or (b) links to a future `/battles/hot` listing. For this iteration, **omit "View all" on HOT** — the rail itself already shows up to 10 tiles, which is sufficient.

## Data model changes

### Migration `add_sponsorship_to_battles_table`

Adds two columns to `battles`:

- `is_sponsored` — `boolean`, default `false`, indexed.
- `sponsor_handle` — `string` (nullable).

Both added to `$fillable` on `App\Models\Battle`. `is_sponsored` added to `$casts` as `bool`.

### `Battle::sponsoredActive()`

Static method on `App\Models\Battle`:

```php
public static function sponsoredActive(): \Illuminate\Database\Eloquent\Collection;
```

Returns active (`STATUS_ACTIVE`) battles with `is_sponsored = true`, ordered by `closes_at asc`, limited to 10. Used only by `BattleIndex`.

### `Battle::resolveFeatured()` removed

The single-featured concept is replaced by the sponsored slider. All callers of `resolveFeatured()` (currently `BattleIndex::render()`) switch to `sponsoredActive()`. Remove the method from the model and any tests that exercise it.

### `Category::battles` relation

If not already present, add `public function battles(): HasMany { return $this->hasMany(Battle::class); }` to `App\Models\Category` — used for eager loading in `BattleIndex::render()`.

### `BattleFactory`

Set `is_sponsored => false` and `sponsor_handle => null` as defaults. Add a `->sponsored(string $handle = '@brand')` state for tests.

## Backend changes

### `App\Livewire\BattleIndex` — rewritten

Remove:
- `#[Url] $category`, `#[Url] $finished`.
- `WithPagination` trait.
- Methods `selectCategory`, `toggleFinished`, `clearFilters`.

New `render()`:

```php
$sponsored = Battle::sponsoredActive();
$sponsoredIds = $sponsored->pluck('id');

$hot = Battle::query()->active()
    ->with('category')
    ->whereNotIn('id', $sponsoredIds)
    ->orderByDesc('total_pool')
    ->limit(10)
    ->get();

$categoryRails = Category::query()
    ->orderBy('sort_order')
    ->with(['battles' => fn ($q) => $q
        ->active()
        ->whereNotIn('id', $sponsoredIds)
        ->orderByDesc('total_pool')
        ->limit(10)
    ])
    ->get()
    ->filter(fn ($c) => $c->battles->isNotEmpty());

return view('livewire.battle-index', compact('sponsored', 'hot', 'categoryRails'));
```

### `App\Livewire\CategoryShow` — new

Path: `/categories/{category:slug}`. Route name: `categories.show`.

```php
class CategoryShow extends Component
{
    use WithPagination;
    public Category $category;

    public function mount(Category $category): void
    {
        $this->category = $category;
    }

    public function nextPage(): void { $this->setPage($this->page + 1, 'page'); }

    #[Layout('layouts.app')]
    public function render(): View
    {
        $battles = Battle::active()
            ->where('category_id', $this->category->id)
            ->with('category')
            ->orderByDesc('closes_at')
            ->paginate(20);

        return view('livewire.category-show', compact('battles'));
    }
}
```

### `routes/web.php`

Add:

```php
Route::get('/categories/{category:slug}', CategoryShow::class)->name('categories.show');
```

Existing routes (`/`, `/battles`, `/battles/{battle:slug}`, `/leaderboard`, etc.) are unchanged.

## View / component changes

### New shared component: `<x-carousel>`

Path: `resources/views/components/carousel.blade.php`.

Props:
- `per-page-mobile` (int, default 2)
- `per-page-desktop` (int, default 4)
- `auto-advance` (bool, default false)
- `interval-ms` (int, default 6000)
- `show-arrows` (bool, default true)
- `show-dots` (bool, default true)

Behavior:
- Container with `overflow-hidden`, inner flex track moved via `translateX`.
- Each slide (direct child of the carousel) gets `flex: 0 0 auto` and width `100 / perPage %`.
- `perPage` recomputed on resize via `matchMedia('(min-width: 1024px)')`.
- `pages = ceil(total / perPage)`. Dots rendered per page; active dot highlighted.
- Arrows are small circular buttons on left/right edges, disabled at the ends (no wrap).
- Touch handling: `touchstart` / `touchmove` / `touchend`; swipes >50px advance by one page.
- `auto-advance` uses `setInterval(intervalMs)`, next() wraps. Paused on `pointerover` / `focusin` / `touchstart`.
- If `total <= perPage`, arrows and dots are hidden (nothing to navigate).

Implementation: Alpine component registered via `Alpine.data('carousel', ...)` in `resources/js/app.js`, so Livewire-navigate re-initializes it reliably.

### New component: `<x-battle-tile :battle="$battle" />`

Path: `resources/views/components/battle-tile.blade.php`.

Replaces the current `<x-battle-row>` on home / category pages.

Structure:
- Outer `<a href="route('battles.show', $battle)">` with `rounded-xl border bg-white/[0.035] p-3` and hover state.
- Top: two square image slots (equal-width flex children) with a "VS" badge overlapping their gap.
  - Each square: `rounded-lg bg-navy-800 aspect-square`. If `side_a_image` / `side_b_image` set → `<img class="object-cover">`, else `<x-icon.image-placeholder>` (new SVG — a stroked mountain/image silhouette).
  - "VS" badge: absolute-centered, `h-9 w-9 rounded-full border border-white/20 bg-navy-900`, text "VS" inside.
- Title: `truncate text-sm text-white/90` — `$battle->title`.
- Metric row: left `💰 {compact($battle->total_pool)}`, right `⏱ {timeLeft($battle)}`.

Helpers:
- `Battle::compactPool()` — instance method returning compact form of `$this->total_pool` ("1500" → "1.5k", "120000" → "120k"). Lives on the model to avoid a new helper file.
- Time-left: inline Carbon expression inside `battle-tile.blade.php` — `$battle->closes_at->diff(now())` formatted as `H:MM`. No helper extracted.

### New component: `<x-battle-featured-card :battle="$battle" />`

Path: `resources/views/components/battle-featured-card.blade.php`.

Larger than `<x-battle-tile>`:
- Outer `rounded-2xl border bg-white/[0.04] p-4 sm:p-5`.
- Top: two larger image slots with centered "VS" badge, side labels (`side_a_label` / `side_b_label`) below each in bold.
- Metric row: `💰 {number_format($total_pool, 0)} VRS` left, `⏱ H:MM:SS` right (finer-grained than tiles).
- Two vote buttons full-width stacked side-by-side: `VOTE {LABEL_A}` and `VOTE {LABEL_B}`. Both are plain `<a>` links to `route('battles.show', $battle)` — clicking takes the user to the battle page where `<livewire:battle-vote-widget>` does the real voting. No `?side=` query param, no pre-selection. **Voting is not embedded** in the slider.
- If `is_sponsored` and `sponsor_handle` present: bottom caption "Sponsored by {handle}" (`text-xs text-white/50 tracking-wide`).

### New component: `<x-icon.image-placeholder />`

Path: `resources/views/components/icon/image-placeholder.blade.php`. Simple SVG — stroked mountain-in-frame silhouette (matches mockup's grey-square placeholder). Used as fallback when `side_*_image` is null.

### Component removals

- `resources/views/livewire/battle-index/featured-card.blade.php` → delete.
- `resources/views/livewire/battle-index/hot-rail.blade.php` → delete (HOT rendered directly in `battle-index.blade.php` using `<x-carousel>`).
- `resources/views/livewire/battle-index/category-chips.blade.php` → delete.
- `resources/views/livewire/battle-index/all-list.blade.php` → delete.
- `resources/views/components/battle-row.blade.php` → delete (no callers remain).

### `livewire/battle-index.blade.php` — rewritten

Top-level structure (inside the existing `lg:grid` 2-column wrapper):

```blade
<div class="min-w-0 space-y-6 pb-6">
    @if ($sponsored->isNotEmpty())
        <x-carousel :per-page-mobile="1" :per-page-desktop="1" :auto-advance="true">
            @foreach ($sponsored as $battle)
                <x-battle-featured-card :battle="$battle" />
            @endforeach
        </x-carousel>
    @endif

    @if ($hot->isNotEmpty())
        <section>
            <header class="flex items-baseline justify-between px-3 mb-2">
                <div class="text-[11px] uppercase tracking-wider text-white/55">{{ __('battle.hot') }}</div>
            </header>
            <x-carousel>
                @foreach ($hot as $battle)
                    <x-battle-tile :battle="$battle" />
                @endforeach
            </x-carousel>
        </section>
    @endif

    @foreach ($categoryRails as $category)
        <section>
            <header class="flex items-baseline justify-between px-3 mb-2">
                <div class="text-[11px] uppercase tracking-wider text-white/55">{{ $category->localized_name }}</div>
                <a href="{{ route('categories.show', $category) }}" class="text-[11px] text-glow-cyan hover:underline">
                    {{ __('battle.view_all') }} ›
                </a>
            </header>
            <x-carousel>
                @foreach ($category->battles as $battle)
                    <x-battle-tile :battle="$battle" />
                @endforeach
            </x-carousel>
        </section>
    @endforeach
</div>
<aside class="hidden lg:block">
    <livewire:sidebar-widgets />
</aside>
```

### `livewire/category-show.blade.php` — new

Same wrapper as home, but content is:
- Header with category name and count of active battles.
- Grid `grid grid-cols-2 lg:grid-cols-4 gap-3` of `<x-battle-tile>`.
- Load-more button bound to `wire:click="nextPage"`.
- Empty state: centered message `__('battle.no_active_in_category')` with a link back to home.

### `layouts/bottom-nav.blade.php` — rewritten

5-tab grid. Central tab is a raised FAB:

```blade
<nav class="fixed bottom-0 inset-x-0 z-40 sm:hidden bg-navy-900/95 backdrop-blur border-t border-white/5 pt-6 pb-[env(safe-area-inset-bottom)]">
    <ul class="grid grid-cols-5 items-end">
        <li>...Home link...</li>
        <li><button disabled aria-disabled="true" title="{{ __('nav.coming_soon') }}" class="... text-white/40 cursor-not-allowed">...Feed...</button></li>
        <li class="flex justify-center">
            <button disabled aria-disabled="true" title="{{ __('nav.coming_soon') }}"
                class="-mt-6 h-14 w-14 rounded-full bg-gradient-to-br from-vote-blue-from to-vote-purple-to shadow-vote-blue text-white flex items-center justify-center">
                <x-icon.plus class="h-6 w-6" />
            </button>
        </li>
        <li>...Leaderboard link...</li>
        <li>...Profile link...</li>
    </ul>
</nav>
```

New files needed:
- `resources/views/components/icon/plus.blade.php` — simple `+` SVG.
- `resources/views/components/icon/feed.blade.php` — newspaper / feed SVG for the Feed tab.

Remove MyBets tab entirely.

## Admin (Filament)

`App\Filament\Admin\Resources\Battles\Schemas\BattleForm`:

- Add `Toggle::make('is_sponsored')->label('Sponsored')` somewhere near the top.
- Add `TextInput::make('sponsor_handle')->label('Sponsor handle')->prefix('@')->visible(fn (Get $get) => (bool) $get('is_sponsored'))`.

No other admin changes.

## i18n

### New keys

`lang/{en,ru}/nav.php`:
- `feed` → "Feed" / "Лента"
- `create` → "Create" / "Создать"
- `coming_soon` → "Coming soon" / "Скоро"

`lang/{en,ru}/battle.php`:
- `sponsored_by` → "Sponsored by :handle" / "Спонсор: :handle"
- `no_active_in_category` → "No active battles in this category" / "В этой категории пока нет активных баталий"
- `vote_for` → "Vote :side" / "За :side"

### Keys to check / possibly remove

Before deletion, grep the codebase for each key. Remove only if no other usage remains.

Candidates for removal (were used by the removed views `all-list`, `category-chips`, `featured-card`, `hot-rail`, `battle-row`):
- `battle.all_battles`
- `battle.all_chip`
- `battle.finished_chip`
- `battle.no_battles`
- `battle.no_battles_in_category`
- `battle.no_settled_battles`
- `battle.refunded_tie`
- `battle.load_more` — probably still needed on `CategoryShow` (keep).
- `battle.featured` — used by `featured-card.blade.php`, which is deleted. Remove if unused elsewhere.

`battle.view_all`, `battle.hot`, `battle.pool`, `battle.winner`, `battle.vote` — still in use, keep.

## Risks and open questions

- **Alpine re-init on Livewire navigation.** The carousel component must register via `Alpine.data('carousel', ...)`. If it uses inline `x-data="{...}"` only, SPA-mode navigation between home and a category page will break re-initialization. Mitigated by the prescribed registration.
- **`BattleShow` side pre-selection.** The featured-card vote buttons link to `route('battles.show', $battle)`; whether they pass `?side=A/B` depends on whether `BattleShow` / `BattleVoteWidget` can read it. Keep this optional — if support isn't trivial, both buttons simply link to the battle page without pre-selection.
- **`hot_battles` vs `hot` i18n key.** Currently `battle.hot` is the header. Keep as-is; no new key.
- **`resolveFeatured()` callers.** Need to confirm via grep that the only caller is `BattleIndex`. If there are tests or admin resources referencing it, update them.
- **Admin test for sponsorship.** Filament's form test setup is already present for other Battle fields; follow the same pattern.
- **Desktop carousel width.** With 4 tiles per page on desktop and `lg:grid-cols-[minmax(0,1fr)_320px]`, the carousel has roughly ~800px available. 4 tiles → ~200px each minus gaps — reasonable. Verify visually.
- **Seed data.** `database/migrations/2026_04_22_130000_seed_home_page_demo_data.php` already seeds battles into categories — those seed battles should work without changes. Optionally add 1–2 sponsored demo battles in a follow-up seed migration, but not required for the feature to work.

## Testing plan

### New Pest tests

- `tests/Feature/Home/HomePageSectionsTest.php` — sponsored slider presence / absence, HOT ordering, HOT excludes sponsored, category rails ordered by `sort_order`, empty categories hidden, sponsored excluded from category rails.
- `tests/Feature/Categories/CategoryShowTest.php` — renders active battles for a category, paginates large lists, shows empty state, returns 404 for unknown slug.
- `tests/Feature/Admin/BattleSponsorshipTest.php` — admin can set `is_sponsored` and `sponsor_handle`.
- `tests/Feature/Navigation/BottomNavTest.php` — 5 tabs rendered, Feed/Create have `aria-disabled="true"`.

### Test updates

Any existing tests that exercise `selectCategory`, `toggleFinished`, `clearFilters`, or `Battle::resolveFeatured()` — update or remove.

### Manual / visual checks

JS carousel behavior (swipe, dots, arrows, auto-advance, pause on hover) is verified manually — either via Playwright MCP or by hand in the browser.

### Pre-commit gate

`make pint && make stan && make test` must pass before any "done" claim.

## Manual QA checklist

- [ ] Mobile home: sponsored slider visible with dots, auto-advance ticks every 6s, pauses on touch.
- [ ] Mobile home: HOT rail shows 2 tiles at a time, dots indicate pages, swipe works.
- [ ] Mobile home: one section per non-empty category, ordered by `sort_order`. "View all" link visible per section.
- [ ] Mobile home: empty categories (0 active battles) are absent from the page.
- [ ] Desktop home: same sections, 4 tiles per page, sidebar still present on the right.
- [ ] Click on a tile navigates to the battle page.
- [ ] Click on "View all" navigates to `/categories/{slug}`.
- [ ] Category page: 2×N grid on mobile, 4×N on desktop, pagination works, empty state correct.
- [ ] Bottom nav: 5 tabs; Feed and Create are disabled-looking with `title="Coming soon"`.
- [ ] Admin can toggle `is_sponsored` on a battle; battle appears in the home slider with "Sponsored by @handle" if handle set.
- [ ] `/my-bets` still loads (direct URL).
- [ ] `make pint && make stan && make test` green.

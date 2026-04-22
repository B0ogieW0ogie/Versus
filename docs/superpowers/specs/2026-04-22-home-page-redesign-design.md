# Home page redesign — mobile-first

Date: 2026-04-22

## Goal

Rework the public landing page (`/`) from a flat desktop-oriented grid of battle cards into a mobile-first, Polymarket-style feed: a featured hero battle on top, a "hot" row of the biggest active battles, category chips as a filter, and a paginated list of the remaining battles. Introduce three new surfaces linked from a mobile bottom nav (Leaderboard, My Bets) and a global search overlay. Keep the existing desktop experience — the sidebar widgets and current top bar — functional by making the same components adapt up to a wider layout.

## Scope

**In:**
- Home page redesign (`BattleIndex`) with Featured / Hot / Categories chips / All sections.
- Admin-managed `categories` taxonomy, one-to-many on `battles`.
- `battles.is_featured` flag with fallback to biggest pool.
- Global search overlay (title + side labels).
- New public page `/leaderboard` ranked by total `payout_win`.
- New auth-guarded page `/my-bets` with Active / Settled tabs.
- Mobile bottom nav (Home, Leaderboard, My Bets, Profile).
- Removal of the mobile hamburger menu (bottom nav replaces it).
- Sync desktop `SidebarWidgets::topPlayers` to the same `payout_win` aggregate used by Leaderboard.

**Out (YAGNI):**
- User-generated battles. Admin remains the only author. The mockup's "+ Create your battle" CTA is dropped entirely.
- Many-to-many categories. A battle has at most one category.
- Custom profile page. The Profile bottom-nav tab links to the existing Laravel Breeze `/profile`.
- Live polling for timers / pool updates beyond what already exists.
- Infinite scroll. The All-battles list uses a "Load more" button (Livewire pagination).
- Category name localisation via lang files. Names live on the `categories` table as `name_en` and `name_ru` columns.

## Architecture choice

**Approach: thin-shell `BattleIndex` with Blade partials.** One Livewire component owns all home-page state (selected category, finished toggle, pagination). Blade partials under `resources/views/livewire/battle-index/` handle visual decomposition. Separate Livewire components exist only where they have their own route or lifecycle:
- `Leaderboard` (own route `/leaderboard`).
- `MyBets` (own route `/my-bets`).
- `SearchOverlay` (global, inserted once in the layout, listens for `open-search` events).

Bottom nav is a Blade partial (`layouts/bottom-nav.blade.php`) included from `layouts/app.blade.php`. No Livewire — it needs no state of its own; balance animation keeps using the existing `balance-updated` Alpine listener.

## Data model

### New table: `categories`

Migration columns:
- `id` bigint PK
- `slug` string, unique, kebab-case, used in URLs and CSS hooks
- `name_en` string
- `name_ru` string
- `sort_order` integer, default 0, indexed — Filament list sort and chip display order
- `created_at`, `updated_at`

Seeder `CategorySeeder` creates: Sports, Memes, Movies, Superheroes, TV Shows (in that `sort_order`). Slugs are kebab-case of the English name (`sports`, `memes`, `movies`, `superheroes`, `tv-shows`).

### `battles` changes

Migration adds two columns:
- `category_id` bigint nullable, FK → `categories.id` with `ON DELETE SET NULL`. Nullable: existing battles have no category until the admin assigns one; they appear only under chip "All".
- `is_featured` boolean, default false, indexed. At most one battle is typically flagged, but the code tolerates multiple (see featured resolution below).

### Models

- `App\Models\Category`:
  - `$fillable = ['slug', 'name_en', 'name_ru', 'sort_order']`
  - `battles(): HasMany`
  - `getLocalizedNameAttribute(): string` — returns `name_en` or `name_ru` based on `app()->getLocale()`; falls back to `name_en` for any other locale.
- `App\Models\Battle`:
  - Add `'category_id'` and `'is_featured'` to `$fillable`; cast `is_featured` to boolean.
  - `category(): BelongsTo`.
  - `scopeActive(Builder $q): Builder` — `where('status', self::STATUS_ACTIVE)`.
  - `scopeFeatured(Builder $q): Builder` — `where('is_featured', true)`.

### Admin (Filament)

- New `App\Filament\Admin\Resources\CategoryResource` with simple CRUD: `slug`, `name_en`, `name_ru`, `sort_order`. Table sorted by `sort_order`.
- `BattleResource` form gains:
  - `Select::make('category_id')` → options from `Category::orderBy('sort_order')`, nullable.
  - `Toggle::make('is_featured')`.
  - Optional hint on `is_featured`: "The flagged battle appears at the top of the home page. If nothing is flagged, the biggest active battle is shown."

### Featured resolution

One place, re-usable:
```php
// App\Models\Battle (static helper)
public static function resolveFeatured(): ?self
{
    return static::query()->active()->featured()->latest('updated_at')->first()
        ?? static::query()->active()->orderByDesc('total_pool')->first();
}
```
`latest('updated_at')` picks the most recently flagged battle if several are flagged. Fallback returns the biggest-pool active battle. Returns `null` only when no active battles exist — in that case the home page hides the Featured section.

## Home page

### Livewire: `App\Livewire\BattleIndex`

Public properties (URL-synced):
- `#[Url] ?string $category = null` — selected category slug; `null` = "All".
- `#[Url] bool $finished = false` — when true, the list shows settled battles across all categories; `$category` is reset to `null`.

Plus `use WithPagination;` (Tailwind paginator).

Actions:
- `selectCategory(?string $slug): void` — `$this->category = $slug; $this->finished = false; $this->resetPage();`
- `toggleFinished(): void` — `$this->finished = true; $this->category = null; $this->resetPage();`
- `clearFilters(): void` — used by Hot section's "View all": `$this->finished = false; $this->category = null; $this->resetPage();`. The partial wraps it with a JS scroll to `#all-battles`.

`render()` produces four variables for the view:
- `$featured` — `Battle::resolveFeatured()`; `null` when no active battles.
- `$hot` — top 3 active by `total_pool`, excluding `$featured?->id`:
  ```php
  Battle::query()->active()
      ->when($featured, fn ($q, $f) => $q->whereKeyNot($f->id))
      ->orderByDesc('total_pool')
      ->limit(3)
      ->get();
  ```
- `$categories` — `Category::orderBy('sort_order')->get()`.
- `$all` — paginated 10 per page:
  - If `$finished`: `Battle::query()->with('category')->whereIn('status', [STATUS_SETTLED, STATUS_CLOSED])->orderByDesc('settled_at')`.
  - Else: `Battle::query()->active()->with('category')` minus featured + hot ids, filtered by `$category` if set:
    ```php
    ->whereNotIn('id', array_filter([$featured?->id, ...$hot->pluck('id')->all()]))
    ->when($category, fn ($q, $slug) =>
        $q->whereHas('category', fn ($qq) => $qq->where('slug', $slug))
    )
    ->orderByDesc('closes_at');
    ```
  - `->paginate(10)`.

### View: `resources/views/livewire/battle-index.blade.php`

Root layout: a single column, `max-w-xl mx-auto` on mobile, `lg:grid lg:grid-cols-[1fr_320px] lg:gap-8 lg:max-w-7xl` on desktop — left column = content, right column on desktop holds the existing `<livewire:sidebar-widgets />`.

Sections include partials from `resources/views/livewire/battle-index/`:
- `featured-card.blade.php` — shown only when `$featured` is not null. Uses the existing `<livewire:battle-vote-widget :battle="$featured" />` for the two vote buttons so voting logic is not duplicated.
- `hot-rail.blade.php` — loops over `$hot`, uses the new shared `<x-battle-row>` component for each card.
- `category-chips.blade.php` — iterates `$categories`, rendering a button per category plus a first "All" chip and a last "Finished" chip. Active state is computed from `$category` / `$finished`. Horizontal scroll on overflow (`overflow-x-auto scrollbar-hide`).
- `all-list.blade.php` — wrapper `<section id="all-battles">`, loops `$all` as `<x-battle-row>`, renders an empty-state message keyed by mode, and shows Livewire's pagination ("Load more" styled link).

### Shared Blade component: `<x-battle-row>`

Used in Hot and All sections and eventually reusable elsewhere. Props: `$battle`, optional `$showVoteButton = true`. Layout: two overlapped circular avatars (slug or initial fallback), title + pool (or + winning side for settled), right column with countdown or settled date plus a VOTE link that routes to `route('battles.show', $battle)`. No inline voting — Featured is the only place with inline voting because it has room.

### Empty states

- No active battles at all: Featured and Hot hidden; All list shows `__('battle.no_battles')`.
- Category selected but empty: `__('battle.no_battles_in_category')`.
- Finished mode empty: `__('battle.no_settled_battles')`.

### "View all" in Hot

Rendered as a link with `wire:click="clearFilters"` plus `x-on:click="$nextTick(() => document.getElementById('all-battles').scrollIntoView({behavior:'smooth'}))"`. Smooth-scrolls to the All section after state resets.

## Search overlay

### Livewire: `App\Livewire\SearchOverlay`

Inserted once into `layouts/app.blade.php` as `<livewire:search-overlay />`.

Public properties:
- `public string $query = ''` — bound with `wire:model.live.debounce.300ms="query"`.
- Alpine wrapper owns the `open` state; it listens for a window-level `open-search` event (dispatched by the top-bar search button) and opens. Escape and the "Cancel" link close it.

Rendered only when Alpine `open` is true (inside `<template x-if="open">`), so the component doesn't bloat the DOM until it's used. Livewire still mounts at initial render for reactivity.

`render()` logic:
- If `strlen(trim($query)) < 2`: return `[]` results. View shows `__('search.min_chars')` when `$query` non-empty, otherwise a neutral empty state.
- Else: run
  ```php
  $q = '%'.mb_strtolower(trim($query)).'%';
  Battle::query()
      ->where(function (Builder $w) use ($q) {
          $w->whereRaw('LOWER(title) LIKE ?', [$q])
            ->orWhereRaw('LOWER(side_a_label) LIKE ?', [$q])
            ->orWhereRaw('LOWER(side_b_label) LIKE ?', [$q]);
      })
      ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")
      ->orderByDesc('total_pool')
      ->limit(15)
      ->get();
  ```
  `LOWER(col) LIKE LOWER(?)` via `whereRaw` works identically on Postgres (dev) and SQLite (`:memory:` tests) — no `ilike`, no Postgres-only operators.

View:
- Fixed full-screen overlay on mobile, centered modal (~560px wide) on desktop.
- Input auto-focused on open, with a keyboard close button labelled Cancel.
- Results as rows: two side avatars, title, `category.localized_name · status badge`, pool. Click closes overlay and navigates to `route('battles.show', $battle)`.

### Top-bar search button

Added to both desktop and mobile top bar in `layouts/navigation.blade.php`. `<button x-on:click="$dispatch('open-search')">` — no route change.

## Leaderboard

### Leaderboard route and component

- `Route::get('/leaderboard', Leaderboard::class)->name('leaderboard');` in `routes/web.php`, outside the auth group (public).
- `App\Livewire\Leaderboard` with `WithPagination`.

### Aggregate query

Rank users by `SUM(transactions.amount)` where `type = Transaction::TYPE_PAYOUT_WIN`:
```php
User::query()
    ->select('users.id', 'users.name')
    ->selectSub(
        Transaction::query()
            ->selectRaw('COALESCE(SUM(amount), 0)')
            ->whereColumn('user_id', 'users.id')
            ->where('type', Transaction::TYPE_PAYOUT_WIN),
        'total_winnings'
    )
    ->orderByDesc('total_winnings')
    ->orderBy('users.id')
    ->limit(100)
    ->get();
```
One query. Tie-breaker on `users.id` keeps ranks deterministic. Returning top 100 without pagination — we can paginate later if needed; v1 is a single list.

### "Your position" row

For authenticated users not in the top 100, compute their rank and winnings separately and render a pinned row below the top list:
```php
$mine = Transaction::query()
    ->where('user_id', auth()->id())
    ->where('type', Transaction::TYPE_PAYOUT_WIN)
    ->sum('amount');
$myRank = User::query()
    ->selectRaw('COUNT(*) as c')
    ->fromSub(/* same aggregate subquery */, 't')
    ->where('total_winnings', '>', $mine)
    ->value('c') + 1;
```

### Sidebar sync

Update `App\Livewire\SidebarWidgets::topPlayers()` (or wherever the current "Top Players" query lives) to use the same aggregate — returning the top 5 users by `payout_win` instead of by `balance`. The mini-widget and the full page thus tell the same story.

### View

Single-column list identical in shape to the Leaderboard mockup: rank badge (gold for 1–3), avatar initial, name, total winnings formatted with `number_format`. Own row highlighted when `auth()->id() === $row->id`. Empty state if no winning payouts exist yet.

## My Bets

### My Bets route and component

- `Route::get('/my-bets', MyBets::class)->middleware('auth')->name('my-bets');`
- `App\Livewire\MyBets` with a `#[Url] string $tab = 'active'` property.

### Query

Load the user's votes (newest first), eager-loading the battle and any relevant payout transactions:
```php
$votes = Vote::query()
    ->where('user_id', auth()->id())
    ->with([
        'battle:id,title,slug,status,side_a_label,side_b_label,winning_side,total_pool,closes_at,settled_at',
    ])
    ->when($this->tab === 'active',
        fn ($q) => $q->whereHas('battle', fn ($b) => $b->where('status', Battle::STATUS_ACTIVE)),
        fn ($q) => $q->whereHas('battle', fn ($b) => $b->whereIn('status', [Battle::STATUS_SETTLED, Battle::STATUS_CLOSED])),
    )
    ->latest()
    ->paginate(20);
```

For Settled rows, compute per-row status and payout in the view (or in a small helper on the component) by matching the `Vote` against the battle's `winning_side`:
- `winning_side` is null on the battle and a `Transaction::TYPE_REFUND` exists for `(user_id, battle_id)` → **Refund**.
- `vote.side === battle.winning_side` → **Won**; payout amount = `Transaction::where(user_id, battle_id, type=TYPE_PAYOUT_WIN)->sum('amount')`. Because a user can cast several votes on the same battle, the payout transaction is per-battle-per-user; show it once on the most recent winning vote, or sum per-vote proportionally. Simplest: show the total payout on the user's most recent winning vote row and a subdued dash on earlier winning rows on the same battle (grouping is explicit on display, not in the query).
- Otherwise → **Lost**; display `−{stake}`.

Emit a single `Transaction`-sum query per page load (`whereIn('battle_id', ...)`) and fold results onto each vote row in PHP, rather than per-row queries.

### View

Tab bar (`Active` / `Settled`) at the top using `wire:click="$set('tab', 'active')"`. Each row is a card:
- Title with battle link.
- `Voted {SIDE}` with the side label colour-cued (winner colour on Won, loser colour on Lost, neutral on Active/Refund).
- Stake, closing countdown (Active) or settled date (Settled).
- Badge (Active / Won / Lost / Refund) and net amount on the right.

Empty states per tab: `__('my_bets.empty_active')` and `__('my_bets.empty_settled')`.

Guests hitting `/my-bets` are redirected to `/login` by the `auth` middleware, which preserves the intended URL.

## Layout shell

### `layouts/app.blade.php`

- Keep `@include('layouts.navigation')` at the top.
- Immediately after `</main>`, insert `@include('layouts.bottom-nav')`.
- Insert `<livewire:search-overlay />` once, as the last child of `<body>`.
- `<main>` gets `pb-20 sm:pb-0` so the mobile bottom nav doesn't cover content.

### `layouts/bottom-nav.blade.php` (new)

```blade
<nav class="fixed bottom-0 inset-x-0 z-40 sm:hidden bg-navy-900 border-t border-white/5">
    <ul class="grid grid-cols-4">
        @foreach ([
            ['route' => 'home',        'match' => ['home', 'battles.*'], 'label' => __('nav.home'),        'icon' => 'home'],
            ['route' => 'leaderboard', 'match' => ['leaderboard'],       'label' => __('nav.leaderboard'), 'icon' => 'trophy'],
            ['route' => 'my-bets',     'match' => ['my-bets'],           'label' => __('nav.my_bets'),    'icon' => 'chart'],
            ['route' => 'profile.edit','match' => ['profile.*'],         'label' => __('nav.profile'),    'icon' => 'user'],
        ] as $tab)
            <li>
                <a href="{{ route($tab['route']) }}"
                   class="flex flex-col items-center gap-1 py-2 text-[10px] {{ request()->routeIs(...$tab['match']) ? 'text-white' : 'text-white/55 hover:text-white' }}">
                    <x-dynamic-component :component="'icon.' . $tab['icon']" class="h-5 w-5" />
                    <span>{{ $tab['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
```

Icons live under `resources/views/components/icon/` as inline SVGs (`home.blade.php`, `trophy.blade.php`, `chart.blade.php`, `user.blade.php`), using the existing stroke-width / color tokens from the project's Tailwind theme.

For guests, `/my-bets` and `/profile` still resolve to the right route name — the auth middleware on those routes produces the standard `/login?redirect=...` behaviour.

### `layouts/navigation.blade.php` changes

- **Mobile (< sm):**
  - Remove the hamburger button and collapsed panel (lines ~100–172 of the current file).
  - Keep logo + app name on the left.
  - Add a search-icon button on the right (`x-on:click="$dispatch('open-search')"`).
  - Keep the balance pill for authenticated users; show a single "Login" link for guests (no "Register" button on mobile — the Login page links out to registration).
- **Desktop (≥ sm):** unchanged, except the same search-icon button is added next to the notifications / messages buttons.

The collapsed-panel previously held a locale switcher and the logout link. Logout already exists on the Breeze `/profile` page, so no work there. The mobile locale switcher is dropped from this iteration — the desktop top bar keeps its switcher; mobile users can change language by navigating to any page on desktop, or a follow-up task can embed `<livewire:locale-switcher />` inside `/profile` without touching this design.

## i18n

Add keys to `lang/en/` and `lang/ru/`:

- `nav.php`: `home`, `leaderboard`, `my_bets`. (`profile` and `battles` already exist.)
- `battle.php` (existing or new): `vote`, `pool`, `closes_in`, `load_more`, `no_battles`, `no_battles_in_category`, `no_settled_battles`, `finished_chip`, `all_chip`.
- `search.php` (new): `open`, `placeholder`, `min_chars`, `no_results`, `cancel`, `results`.
- `leaderboard.php` (new): `title`, `top_winners`, `all_time`, `your_position`, `empty`.
- `my_bets.php` (new): `title`, `tab_active`, `tab_settled`, `voted`, `stake`, `status_active`, `status_won`, `status_lost`, `status_refund`, `empty_active`, `empty_settled`.

Category names live in the `categories` table (`name_en`, `name_ru`) and are accessed via `$category->localized_name`.

## Tests (Pest)

All new tests use `RefreshDatabase` on the class and Factories for setup.

1. `tests/Feature/FeaturedBattleTest.php`
   - A battle with `is_featured = true` is chosen over one with a larger pool.
   - With no flagged battles, the active battle with the largest pool is chosen.
   - Among multiple flagged, the most recently updated is chosen.
   - Settled battles are never returned.
   - Returns null when no active battles exist.

2. `tests/Feature/Livewire/BattleIndexTest.php`
   - Default render: featured populated, hot excludes featured, all excludes featured + hot.
   - Selecting a category filters All and leaves Hot untouched.
   - `toggleFinished` shows settled battles and hides featured/hot.
   - Pagination works (seed ~25 battles, page two reachable).
   - Empty states for no-active-battles, no-category-matches, no-settled.

3. `tests/Feature/Livewire/LeaderboardTest.php`
   - Aggregation ranks users by total `payout_win`; other transaction types don't contribute.
   - Tie-break by user id.
   - Authenticated user outside the top 100 gets a correct `your_position` row.
   - Unauthenticated user sees the list with no pinned row.

4. `tests/Feature/Livewire/MyBetsTest.php`
   - Active tab filters to votes on active battles only.
   - Settled tab: Won status + payout sum matches the `payout_win` transactions on that (user, battle).
   - Settled tab: Lost status when vote.side ≠ battle.winning_side.
   - Settled tab: Refund status when battle has no winning side and a `refund` transaction exists.
   - Guest hitting `/my-bets` redirects to `/login`.

5. `tests/Feature/Livewire/SearchOverlayTest.php`
   - Query under 2 chars returns empty.
   - Matches `title`, `side_a_label`, `side_b_label` case-insensitively.
   - Active battles rank above settled battles with the same match.
   - Returns at most 15 results.

6. `tests/Feature/Http/BottomNavTest.php`
   - Bottom nav partial is rendered in the layout on responses from each of the four routes.
   - Active tab highlight is applied based on the current route.

7. `tests/Feature/CategoryResourceTest.php` (minimal)
   - Admin can create a category; non-admin cannot access `/admin/categories`.

Run `make pint && make stan && make test` before claiming done. `make stan` memory limit: pass `--memory-limit=512M` if the default crashes (see [CLAUDE.md](../../../CLAUDE.md)).

## Implementation order

1. **Data model.** Migrations for `categories` and `battles` additions; `Category` model; `Battle` updates; `CategorySeeder`. Run `make migrate` + seed.
2. **Admin.** `CategoryResource`; `BattleResource` field additions. Smoke-test in `/admin`.
3. **Shared `<x-battle-row>` component** used by Hot and All. Extract before redesigning `BattleIndex` so both sections consume it from day one.
4. **`BattleIndex` redesign.** New properties, render, partials, view. Keep the old Livewire component signature compatible (route still points at it). Tests.
5. **Layout shell.** New `bottom-nav` partial; changes to `navigation.blade.php`; `app.blade.php` wiring. Icons. Tests.
6. **`SearchOverlay`.** Livewire + Alpine wrapper. Top-bar button. Tests.
7. **Leaderboard.** Route, component, view. Update `SidebarWidgets::topPlayers` to the same aggregate. Tests.
8. **My Bets.** Route, component, view, tab logic. Tests.
9. **i18n pass** — ensure every new string has both `en` and `ru`. Run `php artisan lang:missing` if installed, otherwise grep.
10. **`make pint && make stan && make test`** green.

## Notes for the plan author

- Featured resolution should land as a static method on `Battle`, not inline in the component — both `BattleIndex` and potential future surfaces (API, admin dashboard) will want it.
- The existing `BattleVoteWidget` keeps doing the actual vote — it was just redesigned in [2026-04-19-polymarket-style-vote-widget-design.md](2026-04-19-polymarket-style-vote-widget-design.md). Featured re-uses it as-is.
- `max_vote_amount` is enforced inside `CastVoteAction` — no additional guard needed at the UI level here.
- Money rounding, residue absorption, and system-account semantics are unchanged.

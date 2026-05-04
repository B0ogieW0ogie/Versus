# User-created battles — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let authenticated users create `active` battles from `/battles/create`, then show a 5s “AI is checking…” modal (emulated), set `ai_screened_at`, and redirect to `battles.show`.

**Architecture:** Domain insert lives in `App\Actions\Battles\CreateUserBattleAction` (single responsibility, explicit column list, no user-controlled `status`/`total_pool`). `BattleCreate` Livewire validates, calls the action, tracks `createdBattleId` for the modal, and exposes `completeAiScreening(int $battleId)` for the post-delay round-trip. Client-side `setTimeout(5000)` invokes the Livewire method via `$wire` (Livewire 4 `js()` from the component).

**Tech Stack:** Laravel 13, Livewire 4, Pest 4, Tailwind, existing Breeze auth, PostgreSQL dev / SQLite memory tests.

**Spec:** [docs/superpowers/specs/2026-05-04-user-battle-create-design.md](../specs/2026-05-04-user-battle-create-design.md)

---

## File map

| File | Role |
|------|------|
| `database/migrations/2026_05_04_*_add_ai_screened_at_to_battles_table.php` | Add nullable `timestamp('ai_screened_at')`. |
| `app/Models/Battle.php` | `Fillable` + `casts` + `@property` for `ai_screened_at`. |
| `database/factories/BattleFactory.php` | Default `'ai_screened_at' => null`. |
| `app/Actions/Battles/CreateUserBattleAction.php` | `__invoke(User $user, array $data): Battle` — slug uniquify, `Battle::create([...])`. |
| `app/Livewire/BattleCreate.php` | Form state, `store()`, `completeAiScreening()`, `WithFileUploads` if images enabled. |
| `resources/views/livewire/battle-create.blade.php` | Form UI, modal, `@script` or `$this->js` for 5s timer. |
| `lang/en/battle.php`, `lang/ru/battle.php` | Labels + `ai_checking` (EN string matches spec for `en`; RU can mirror or translate). |
| `tests/Feature/Battles/UserBattleCreateTest.php` | Create defaults, screening, 403, guest. |

**No changes** required to `routes/web.php` if `battles.create` already exists (verify). Filament listing of `ai_screened_at` is optional (YAGNI).

---

### Task 1: Schema and model

**Files:**
- Create: `database/migrations/2026_05_04_160000_add_ai_screened_at_to_battles_table.php`
- Modify: `app/Models/Battle.php`
- Modify: `database/factories/BattleFactory.php`

- [ ] **Step 1: Add migration**

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
            $table->timestamp('ai_screened_at')->nullable()->after('settled_at');
        });
    }

    public function down(): void
    {
        Schema::table('battles', function (Blueprint $table) {
            $table->dropColumn('ai_screened_at');
        });
    }
};
```

- [ ] **Step 2: Update `Battle` model**

Add `'ai_screened_at'` to the `#[Fillable([...])]` list (or set only via `forceFill` in `completeAiScreening` — pick one approach; **recommended:** include in fillable for simplicity with `forceFill` in one place).

In `casts()`:

```php
'ai_screened_at' => 'datetime',
```

In the class docblock add:

```php
 * @property \Illuminate\Support\Carbon|null $ai_screened_at
```

- [ ] **Step 3: Factory default**

In `BattleFactory::definition()`:

```php
'ai_screened_at' => null,
```

- [ ] **Step 4: Migrate in workspace**

Run: `make art CMD="migrate --no-interaction"`  
Expected: migration applies cleanly.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_05_04_160000_add_ai_screened_at_to_battles_table.php app/Models/Battle.php database/factories/BattleFactory.php
git commit -m "feat(battles): add ai_screened_at for user-created flow"
```

---

### Task 2: Failing feature test — `store` creates battle

**Files:**
- Create: `tests/Feature/Battles/UserBattleCreateTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Battles;

use App\Livewire\BattleCreate;
use App\Models\Battle;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserBattleCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_battle_with_expected_defaults(): void
    {
        $user = User::factory()->create();
        $closesAt = now()->addDays(7)->seconds(0);

        Livewire::actingAs($user)
            ->test(BattleCreate::class)
            ->set('title', 'Tabs vs Spaces')
            ->set('description', 'Editor wars.')
            ->set('side_a_label', 'Tabs')
            ->set('side_b_label', 'Spaces')
            ->set('closes_at', $closesAt->format('Y-m-d\TH:i'))
            ->call('store')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('battles', [
            'title' => 'Tabs vs Spaces',
            'status' => Battle::STATUS_ACTIVE,
            'created_by_id' => $user->id,
            'total_pool' => '0.00',
            'is_sponsored' => false,
            'winning_side' => null,
        ]);

        $battle = Battle::query()->where('title', 'Tabs vs Spaces')->first();
        $this->assertNotNull($battle);
        $this->assertNull($battle->ai_screened_at);
        $this->assertNotEmpty($battle->slug);
    }
}
```

Adjust `closes_at` wire format if your datetime-local binding differs (match how Livewire binds `wire:model` on the create form).

- [ ] **Step 2: Run test — expect failure**

Run: `make ws` then `vendor/bin/pest tests/Feature/Battles/UserBattleCreateTest.php`  
(or from host if workspace is up: same path inside container)

Expected: FAIL — `store` not found or validation error.

- [ ] **Step 3: Commit test**

```bash
git add tests/Feature/Battles/UserBattleCreateTest.php
git commit -m "test(battles): add user battle create feature test"
```

---

### Task 3: `CreateUserBattleAction`

**Files:**
- Create: `app/Actions/Battles/CreateUserBattleAction.php`

- [ ] **Step 1: Implement action**

Validated `$data` keys expected (all optional except where noted by caller): `title`, `description`, `side_a_label`, `side_b_label`, `side_a_subtitle`, `side_b_subtitle`, `side_a_image`, `side_b_image`, `opens_at`, `closes_at`, `category_id`.

Slug: `Str::slug($data['title'])` then while `Battle::where('slug', $slug)->exists()`, append `-` + `Str::lower(Str::random(4))`.

```php
<?php

namespace App\Actions\Battles;

use App\Models\Battle;
use App\Models\User;
use Illuminate\Support\Str;

class CreateUserBattleAction
{
    /**
     * @param  array<string, mixed>  $data  Already validated; must not contain status/total_pool/is_sponsored keys from the client.
     */
    public function __invoke(User $user, array $data): Battle
    {
        $baseSlug = Str::slug($data['title']);
        $slug = $baseSlug;
        while (Battle::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.Str::lower(Str::random(4));
        }

        return Battle::query()->create([
            'slug' => $slug,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'side_a_label' => $data['side_a_label'],
            'side_b_label' => $data['side_b_label'],
            'side_a_subtitle' => $data['side_a_subtitle'] ?? null,
            'side_b_subtitle' => $data['side_b_subtitle'] ?? null,
            'side_a_image' => $data['side_a_image'] ?? null,
            'side_b_image' => $data['side_b_image'] ?? null,
            'opens_at' => $data['opens_at'] ?? null,
            'closes_at' => $data['closes_at'] ?? null,
            'status' => Battle::STATUS_ACTIVE,
            'total_pool' => 0,
            'winning_side' => null,
            'settled_at' => null,
            'created_by_id' => $user->id,
            'category_id' => $data['category_id'] ?? null,
            'is_sponsored' => false,
            'sponsor_handle' => null,
        ]);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Actions/Battles/CreateUserBattleAction.php
git commit -m "feat(battles): add CreateUserBattleAction for public create flow"
```

---

### Task 4: `BattleCreate` Livewire — `store` + validation

**Files:**
- Modify: `app/Livewire/BattleCreate.php`

- [ ] **Step 1: Public properties and `store()`**

Use typed public properties aligned with the test (`$title`, `$description`, `$side_a_label`, `$side_b_label`, optional subtitles, `$opens_at`, `$closes_at`, `$category_id` as nullable scalars; images use `WithFileUploads` + `#[Validate]` or validate in `store()`).

Example validation rules inside `store()`:

```php
$this->validate([
    'title' => ['required', 'string', 'max:255'],
    'description' => ['nullable', 'string', 'max:5000'],
    'side_a_label' => ['required', 'string', 'max:255'],
    'side_b_label' => ['required', 'string', 'max:255'],
    'side_a_subtitle' => ['nullable', 'string', 'max:120'],
    'side_b_subtitle' => ['nullable', 'string', 'max:120'],
    'side_a_image' => ['nullable', 'image', 'max:2048'],
    'side_b_image' => ['nullable', 'image', 'max:2048'],
    'opens_at' => ['nullable', 'date'],
    'closes_at' => ['required', 'date', 'after:now'],
    'category_id' => ['nullable', 'exists:categories,id'],
]);
```

If `opens_at` present: add `after:opens_at` on `closes_at`.

After validation, store images like Filament (public disk path strings) — **reuse the same disk and path convention** as admin `BattleForm` (inspect how Filament stores `side_a_image`; mirror `store('battles', 'public')` or project convention).

```php
$battle = app(CreateUserBattleAction::class)($this->getUser(), [
    'title' => $this->title,
    // ... map properties, including stored paths for images
]);
$this->createdBattleId = $battle->id;
$this->showAiModal = true;
```

Add `public ?int $createdBattleId = null;` and `public bool $showAiModal = false;`.

`getUser()` should return `Auth::user()` asserted non-null (route is `auth`).

- [ ] **Step 2: Run Pest for Task 2 test**

Expected: PASS (or fix binding for `closes_at`).

- [ ] **Step 3: Commit**

```bash
git add app/Livewire/BattleCreate.php
git commit -m "feat(battles): implement BattleCreate store with CreateUserBattleAction"
```

---

### Task 5: `completeAiScreening` + tests

**Files:**
- Modify: `app/Livewire/BattleCreate.php`
- Modify: `tests/Feature/Battles/UserBattleCreateTest.php`

- [ ] **Step 1: Add method to Livewire component**

```php
use Illuminate\Support\Facades\Auth;

public function completeAiScreening(int $battleId): mixed
{
    $battle = Battle::query()->findOrFail($battleId);

    abort_unless((int) $battle->created_by_id === (int) Auth::id(), 403);

    if ($battle->ai_screened_at === null) {
        $battle->forceFill(['ai_screened_at' => now()])->save();
    }

    return $this->redirect(route('battles.show', $battle), navigate: true);
}
```

- [ ] **Step 2: Add tests**

Append to `UserBattleCreateTest.php`:

```php
public function test_complete_ai_screening_sets_timestamp_and_redirects(): void
{
    $user = User::factory()->create();
    $battle = Battle::factory()->for($user, 'creator')->create([
        'ai_screened_at' => null,
    ]);

    Livewire::actingAs($user)
        ->test(BattleCreate::class)
        ->call('completeAiScreening', $battle->id)
        ->assertRedirect(route('battles.show', $battle));

    $this->assertNotNull($battle->fresh()->ai_screened_at);
}

public function test_complete_ai_screening_forbidden_for_non_creator(): void
{
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $battle = Battle::factory()->for($owner, 'creator')->create();

    Livewire::actingAs($other)
        ->test(BattleCreate::class)
        ->call('completeAiScreening', $battle->id)
        ->assertForbidden();
}
```

Ensure `BattleFactory` / model supports `for($user, 'creator')` — `creator()` is `belongsTo(User::class, 'created_by_id')`, so use `Battle::factory()->create(['created_by_id' => $owner->id])` if `for()` is awkward.

- [ ] **Step 3: Run tests**

Expected: both PASS.

- [ ] **Step 4: Commit**

```bash
git add app/Livewire/BattleCreate.php tests/Feature/Battles/UserBattleCreateTest.php
git commit -m "feat(battles): completeAiScreening sets ai_screened_at and redirects"
```

---

### Task 6: Blade — form, modal, 5s `js()` timer

**Files:**
- Modify: `resources/views/livewire/battle-create.blade.php`

- [ ] **Step 1: Build UI**

- Outer layout: match other Livewire pages (`max-w-2xl mx-auto px-4 py-12`, dark theme classes consistent with `battle-show` / `battle-index`).
- Form: wire:submit.prevent="store", fields for all validated inputs; category `<select>` options from `Category::orderBy('sort_order')` — pass from `render()` as `$categories` or computed property.
- Modal: `x-show` / `@if($showAiModal)` with `fixed inset-0 z-50`, dimmed backdrop, centered card, CSS spinner (e.g. `border-2 border-white/20 border-t-cyan-400 rounded-full animate-spin w-10 h-10`).
- Body text: use `__('battle.ai_checking')` where `en` value is exactly `AI is checking your battle...` per spec.

- [ ] **Step 2: Start timer when modal opens**

In `BattleCreate`, after setting `showAiModal = true` in `store()`, dispatch JS once. Livewire 4 pattern in `store()` tail:

```php
$this->js(<<<JS
    setTimeout(() => \$wire.completeAiScreening({$this->createdBattleId}), 5000);
JS);
```

**Security note:** `$this->createdBattleId` is an int from DB right after create — safe to interpolate. Do not interpolate raw request input.

Alternative: `@script` in Blade listening for `modal-opened` — either is fine; one place is enough.

- [ ] **Step 3: Manual smoke**

Log in, open `/battles/create`, submit valid form, wait 5s, land on battle page, confirm `ai_screened_at` in DB (optional `make ws` + `tinker`).

- [ ] **Step 4: Commit**

```bash
git add resources/views/livewire/battle-create.blade.php app/Livewire/BattleCreate.php
git commit -m "feat(battles): create form, AI check modal, 5s emulated delay"
```

---

### Task 7: i18n cleanup

**Files:**
- Modify: `lang/en/battle.php`
- Modify: `lang/ru/battle.php`

- [ ] **Step 1: Keys**

Add `'ai_checking' => 'AI is checking your battle...'` to `en`.

Add equivalent key to `ru` (product choice: same English string, or Russian translation — pick one and stay consistent).

Replace `create_placeholder` text with a short line that describes the real form (not “will appear here”).

- [ ] **Step 2: Commit**

```bash
git add lang/en/battle.php lang/ru/battle.php
git commit -m "i18n(battle): ai_checking copy and create page strings"
```

---

### Task 8: Idempotency test + guest guard (if missing)

**Files:**
- Modify: `tests/Feature/Battles/UserBattleCreateTest.php`

- [ ] **Step 1: Idempotent `completeAiScreening`**

```php
public function test_complete_ai_screening_is_idempotent(): void
{
    $user = User::factory()->create();
    $battle = Battle::factory()->create([
        'created_by_id' => $user->id,
        'ai_screened_at' => now()->subHour(),
    ]);
    $first = $battle->ai_screened_at;

    Livewire::actingAs($user)
        ->test(BattleCreate::class)
        ->call('completeAiScreening', $battle->id)
        ->assertRedirect(route('battles.show', $battle));

    $this->assertEquals($first?->timestamp, $battle->fresh()->ai_screened_at?->timestamp);
}
```

Guest redirect is already covered in `HeaderCreateBattleLinkTest`; no duplicate unless you remove coupling.

- [ ] **Step 2: Commit**

```bash
git add tests/Feature/Battles/UserBattleCreateTest.php
git commit -m "test(battles): idempotent completeAiScreening"
```

---

### Task 9: Verification gate

- [ ] **Step 1: Pint**

Run: `make pint`  
Expected: exit 0.

- [ ] **Step 2: Larastan**

Run: `make stan`  
Expected: exit 0; fix PHPStan issues (e.g. `Auth::user()` nullability) with early return or `assert()` if appropriate.

- [ ] **Step 3: Full test suite**

Run: `make test`  
Expected: all green.

- [ ] **Step 4: Final commit** (only if fixes were needed)

```bash
git add -A
git commit -m "chore: pint/stan fixes for user battle create"
```

---

## Self-review (plan vs spec)

| Spec item | Task |
|-----------|------|
| Auth route + Livewire | Already routed; Tasks 4–6 |
| User field subset + server defaults | Task 3–4 |
| Immediate `active` | Task 3 |
| Modal + 5s + exact EN copy | Task 6–7 |
| Single `completeAiScreening` | Task 5 |
| `ai_screened_at` nullable + migration | Task 1 |
| Creator-only on completion | Task 5 |
| No rate limiter | Not planned |
| Tests | Tasks 2, 5, 8 |

**Placeholder scan:** None intentional.

**Signature consistency:** `completeAiScreening(int $battleId)` matches JS call and tests.

---

## Self-review (inline)

- [x] Spec coverage table complete.
- [x] No `TBD` steps.
- [x] `closes_at` required `after:now` matches `Battle::isOpenForVoting()` needing future `closes_at` for voting UX.

---

**Plan complete and saved to `docs/superpowers/plans/2026-05-04-user-battle-create.md`. Two execution options:**

**1. Subagent-Driven (recommended)** — dispatch a fresh subagent per task, review between tasks, fast iteration.

**2. Inline Execution** — run tasks in this session using executing-plans-style checkpoints.

**Which approach do you want?**

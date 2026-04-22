# Home Page Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rework the Versus home page from a flat battle grid into a mobile-first, Polymarket-style feed (Featured / Hot / Categories / All), add mobile bottom navigation, and introduce Leaderboard, My Bets, and global Search surfaces. All per [docs/superpowers/specs/2026-04-22-home-page-redesign-design.md](../specs/2026-04-22-home-page-redesign-design.md).

**Architecture:** Thin-shell `BattleIndex` Livewire component composed of Blade partials. Separate Livewire components (`SearchOverlay`, `Leaderboard`, `MyBets`) only where they have their own route or lifecycle. Bottom nav is a static Blade partial included from the layout. Categories are a small admin-managed taxonomy; `battles.is_featured` controls the hero card with a "biggest active pool" fallback.

**Tech Stack:** Laravel 13 · Livewire 4 · Filament 5 · Blade · Tailwind CSS · PostgreSQL 16 (dev) / SQLite `:memory:` (tests) · Pest 4 · Larastan. All runtime commands run inside the `workspace` Docker container (see [Makefile](../../../Makefile)).

**Conventions for this plan:**

- Every shell command is written as the Docker-exec form (`docker compose exec workspace ...`). Equivalent `make` targets (`make art CMD=...`, `make test`, etc.) also work.
- Tests use PHPUnit-style classes with `use RefreshDatabase;` (see [tests/Feature/Livewire/BattleVoteWidgetTest.php](../../../tests/Feature/Livewire/BattleVoteWidgetTest.php)) — the project's Pest config does NOT apply `RefreshDatabase` globally.
- Commit after each task using the message template at the bottom of the task. The task is not complete until tests pass AND the commit lands.
- Do not add comments to code unless they document a non-obvious constraint.
- Money is `decimal:2`; round with `round($x, 2)` at writes.

---

## Phase 1 — Data layer

### Task 1: `categories` migration, model, factory

**Files:**

- Create: `database/migrations/2026_04_22_120000_create_categories_table.php`
- Create: `app/Models/Category.php`
- Create: `database/factories/CategoryFactory.php`
- Create: `tests/Feature/CategoryModelTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Feature/CategoryModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_localized_name_returns_english_by_default(): void
    {
        app()->setLocale('en');
        $cat = Category::factory()->create(['name_en' => 'Sports', 'name_ru' => 'Спорт']);

        $this->assertSame('Sports', $cat->localized_name);
    }

    public function test_localized_name_returns_russian_when_locale_is_ru(): void
    {
        app()->setLocale('ru');
        $cat = Category::factory()->create(['name_en' => 'Sports', 'name_ru' => 'Спорт']);

        $this->assertSame('Спорт', $cat->localized_name);
    }

    public function test_localized_name_falls_back_to_english_for_unknown_locale(): void
    {
        app()->setLocale('fr');
        $cat = Category::factory()->create(['name_en' => 'Sports', 'name_ru' => 'Спорт']);

        $this->assertSame('Sports', $cat->localized_name);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker compose exec workspace php artisan test --filter=CategoryModelTest
```

Expected: errors because `App\Models\Category` does not exist and the `categories` table has no migration.

- [ ] **Step 3: Write the migration**

`database/migrations/2026_04_22_120000_create_categories_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name_en');
            $table->string('name_ru');
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
```

- [ ] **Step 4: Write the model**

`app/Models/Category.php`:

```php
<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name_en', 'name_ru', 'sort_order'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * @return HasMany<Battle, $this>
     */
    public function battles(): HasMany
    {
        return $this->hasMany(Battle::class);
    }

    public function getLocalizedNameAttribute(): string
    {
        return app()->getLocale() === 'ru' ? $this->name_ru : $this->name_en;
    }
}
```

- [ ] **Step 5: Write the factory**

`database/factories/CategoryFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'slug' => Str::slug($name).'-'.Str::random(4),
            'name_en' => ucfirst($name),
            'name_ru' => ucfirst($name),
            'sort_order' => 0,
        ];
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

```bash
docker compose exec workspace php artisan test --filter=CategoryModelTest
```

Expected: 3 green tests.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_04_22_120000_create_categories_table.php \
        app/Models/Category.php \
        database/factories/CategoryFactory.php \
        tests/Feature/CategoryModelTest.php
git commit -m "Add categories table, model, factory"
```

---

### Task 2: `CategorySeeder` with initial taxonomy

**Files:**

- Create: `database/seeders/CategorySeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Create: `tests/Feature/CategorySeederTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Feature/CategorySeederTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_five_categories_in_order(): void
    {
        $this->seed(CategorySeeder::class);

        $this->assertSame(5, Category::count());

        $slugs = Category::query()->orderBy('sort_order')->pluck('slug')->all();
        $this->assertSame(['sports', 'memes', 'movies', 'superheroes', 'tv-shows'], $slugs);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(CategorySeeder::class);

        $this->assertSame(5, Category::count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker compose exec workspace php artisan test --filter=CategorySeederTest
```

Expected: class not found.

- [ ] **Step 3: Write the seeder**

`database/seeders/CategorySeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $seeds = [
            ['slug' => 'sports',      'name_en' => 'Sports',      'name_ru' => 'Спорт',     'sort_order' => 10],
            ['slug' => 'memes',       'name_en' => 'Memes',       'name_ru' => 'Мемы',      'sort_order' => 20],
            ['slug' => 'movies',      'name_en' => 'Movies',      'name_ru' => 'Фильмы',    'sort_order' => 30],
            ['slug' => 'superheroes', 'name_en' => 'Superheroes', 'name_ru' => 'Супергерои','sort_order' => 40],
            ['slug' => 'tv-shows',    'name_en' => 'TV Shows',    'name_ru' => 'Сериалы',   'sort_order' => 50],
        ];

        foreach ($seeds as $row) {
            Category::query()->updateOrCreate(['slug' => $row['slug']], $row);
        }
    }
}
```

- [ ] **Step 4: Wire it into `DatabaseSeeder`**

Modify `database/seeders/DatabaseSeeder.php` by inserting a call to `$this->call(CategorySeeder::class);` as the first statement of `run()` (before admin/battle seeds), and adding `use Database\Seeders\CategorySeeder;` at the top:

```php
use Database\Seeders\CategorySeeder;
// ...
public function run(): void
{
    $this->call(CategorySeeder::class);

    $admin = User::firstOrCreate(/* ... unchanged ... */);
    // rest unchanged
}
```

- [ ] **Step 5: Run test to verify it passes**

```bash
docker compose exec workspace php artisan test --filter=CategorySeederTest
```

Expected: 2 green tests.

- [ ] **Step 6: Commit**

```bash
git add database/seeders/CategorySeeder.php \
        database/seeders/DatabaseSeeder.php \
        tests/Feature/CategorySeederTest.php
git commit -m "Seed initial battle categories"
```

---

### Task 3: Add `category_id` + `is_featured` to `battles`; update model

**Files:**

- Create: `database/migrations/2026_04_22_120500_add_category_and_featured_to_battles_table.php`
- Modify: `app/Models/Battle.php`
- Modify: `database/factories/BattleFactory.php`
- Create: `tests/Feature/BattleScopesTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Feature/BattleScopesTest.php`:

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
        Battle::factory()->create(['status' => Battle::STATUS_ACTIVE]);
        Battle::factory()->draft()->create();
        Battle::factory()->settled()->create();

        $this->assertSame(1, Battle::query()->active()->count());
    }

    public function test_featured_scope_returns_only_flagged_battles(): void
    {
        Battle::factory()->create(['is_featured' => true]);
        Battle::factory()->create(['is_featured' => false]);
        Battle::factory()->create();

        $this->assertSame(1, Battle::query()->featured()->count());
    }

    public function test_category_relation(): void
    {
        $cat = Category::factory()->create();
        $battle = Battle::factory()->create(['category_id' => $cat->id]);

        $this->assertTrue($battle->category->is($cat));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker compose exec workspace php artisan test --filter=BattleScopesTest
```

Expected: unknown column `is_featured` / undefined scope `active`.

- [ ] **Step 3: Write the migration**

`database/migrations/2026_04_22_120500_add_category_and_featured_to_battles_table.php`:

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
            $table->foreignId('category_id')->nullable()
                ->constrained('categories')
                ->nullOnDelete();
            $table->boolean('is_featured')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('battles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('is_featured');
        });
    }
};
```

- [ ] **Step 4: Update the `Battle` model**

Modify `app/Models/Battle.php`. Add `'category_id'` and `'is_featured'` to the `#[Fillable([...])]` attribute (at the end of the list). In the `casts()` method, add `'is_featured' => 'boolean'`. Add a `category()` relation and two scopes:

```php
// Add these imports at the top if missing:
use Illuminate\Database\Eloquent\Builder;

// Inside #[Fillable([...])] list, add:
//     'category_id',
//     'is_featured',

// Inside casts(), add:
//     'is_featured' => 'boolean',

/**
 * @return BelongsTo<Category, $this>
 */
public function category(): BelongsTo
{
    return $this->belongsTo(Category::class);
}

/**
 * @param Builder<self> $query
 * @return Builder<self>
 */
public function scopeActive(Builder $query): Builder
{
    return $query->where('status', self::STATUS_ACTIVE);
}

/**
 * @param Builder<self> $query
 * @return Builder<self>
 */
public function scopeFeatured(Builder $query): Builder
{
    return $query->where('is_featured', true);
}
```

- [ ] **Step 5: Update `BattleFactory`**

Modify `database/factories/BattleFactory.php`: add `'category_id' => null,` and `'is_featured' => false,` to the returned array in `definition()`.

- [ ] **Step 6: Run test to verify it passes**

```bash
docker compose exec workspace php artisan test --filter=BattleScopesTest
```

Expected: 3 green tests.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_04_22_120500_add_category_and_featured_to_battles_table.php \
        app/Models/Battle.php \
        database/factories/BattleFactory.php \
        tests/Feature/BattleScopesTest.php
git commit -m "Add category_id and is_featured to battles"
```

---

### Task 4: `Battle::resolveFeatured()` with fallback

**Files:**

- Modify: `app/Models/Battle.php`
- Create: `tests/Feature/FeaturedBattleTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Feature/FeaturedBattleTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Battle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeaturedBattleTest extends TestCase
{
    use RefreshDatabase;

    public function test_flagged_battle_beats_bigger_pool(): void
    {
        Battle::factory()->create(['total_pool' => 9999, 'is_featured' => false]);
        $flagged = Battle::factory()->create(['total_pool' => 100, 'is_featured' => true]);

        $this->assertTrue(Battle::resolveFeatured()->is($flagged));
    }

    public function test_fallback_picks_biggest_active_pool_when_none_flagged(): void
    {
        Battle::factory()->create(['total_pool' => 500]);
        $biggest = Battle::factory()->create(['total_pool' => 2000]);
        Battle::factory()->create(['total_pool' => 100]);

        $this->assertTrue(Battle::resolveFeatured()->is($biggest));
    }

    public function test_most_recently_updated_flag_wins_when_multiple(): void
    {
        $older = Battle::factory()->create(['is_featured' => true]);
        $older->forceFill(['updated_at' => now()->subHour()])->save();
        $newer = Battle::factory()->create(['is_featured' => true]);
        $newer->forceFill(['updated_at' => now()])->save();

        $this->assertTrue(Battle::resolveFeatured()->is($newer));
    }

    public function test_settled_battles_are_never_featured(): void
    {
        Battle::factory()->settled()->create(['is_featured' => true, 'total_pool' => 999]);
        $active = Battle::factory()->create(['total_pool' => 10]);

        $this->assertTrue(Battle::resolveFeatured()->is($active));
    }

    public function test_returns_null_when_no_active_battles(): void
    {
        Battle::factory()->settled()->create(['is_featured' => true]);
        Battle::factory()->draft()->create();

        $this->assertNull(Battle::resolveFeatured());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker compose exec workspace php artisan test --filter=FeaturedBattleTest
```

Expected: `Battle::resolveFeatured` does not exist.

- [ ] **Step 3: Implement the method on `Battle`**

Add to `app/Models/Battle.php`:

```php
public static function resolveFeatured(): ?self
{
    return static::query()->active()->featured()->latest('updated_at')->first()
        ?? static::query()->active()->orderByDesc('total_pool')->first();
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
docker compose exec workspace php artisan test --filter=FeaturedBattleTest
```

Expected: 5 green tests.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Battle.php tests/Feature/FeaturedBattleTest.php
git commit -m "Add Battle::resolveFeatured with biggest-pool fallback"
```

---

## Phase 2 — Admin

### Task 5: Filament `CategoryResource`

**Files:**

- Create: `app/Filament/Admin/Resources/Categories/CategoryResource.php`
- Create: `app/Filament/Admin/Resources/Categories/Schemas/CategoryForm.php`
- Create: `app/Filament/Admin/Resources/Categories/Tables/CategoriesTable.php`
- Create: `app/Filament/Admin/Resources/Categories/Pages/ListCategories.php`
- Create: `app/Filament/Admin/Resources/Categories/Pages/CreateCategory.php`
- Create: `app/Filament/Admin/Resources/Categories/Pages/EditCategory.php`
- Create: `tests/Feature/Filament/CategoryResourceTest.php`

Read [app/Filament/Admin/Resources/Battles/BattleResource.php](../../../app/Filament/Admin/Resources/Battles/BattleResource.php) as a template — mirror its structure.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Filament/CategoryResourceTest.php`:

```php
<?php

namespace Tests\Feature\Filament;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_categories(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Category::factory()->create(['name_en' => 'Sports']);

        $this->actingAs($admin)
            ->get('/admin/categories')
            ->assertOk()
            ->assertSee('Sports');
    }

    public function test_non_admin_cannot_access_categories_panel(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get('/admin/categories')
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker compose exec workspace php artisan test --filter=CategoryResourceTest
```

Expected: 404 because the route is not registered.

- [ ] **Step 3: Write `CategoryResource`**

`app/Filament/Admin/Resources/Categories/CategoryResource.php`:

```php
<?php

namespace App\Filament\Admin\Resources\Categories;

use App\Filament\Admin\Resources\Categories\Pages\CreateCategory;
use App\Filament\Admin\Resources\Categories\Pages\EditCategory;
use App\Filament\Admin\Resources\Categories\Pages\ListCategories;
use App\Filament\Admin\Resources\Categories\Schemas\CategoryForm;
use App\Filament\Admin\Resources\Categories\Tables\CategoriesTable;
use App\Models\Category;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Категории';

    protected static ?string $modelLabel = 'Категория';

    protected static ?string $pluralModelLabel = 'Категории';

    public static function form(Schema $schema): Schema
    {
        return CategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 4: Write the form schema**

`app/Filament/Admin/Resources/Categories/Schemas/CategoryForm.php`:

```php
<?php

namespace App\Filament\Admin\Resources\Categories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name_en')
                ->label('Название (EN)')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, callable $set, callable $get) {
                    if ($state !== null && blank($get('slug'))) {
                        $set('slug', Str::slug($state));
                    }
                }),
            TextInput::make('name_ru')
                ->label('Название (RU)')
                ->required(),
            TextInput::make('slug')
                ->label('Слаг')
                ->required()
                ->unique(ignoreRecord: true),
            TextInput::make('sort_order')
                ->label('Порядок')
                ->numeric()
                ->default(0)
                ->required(),
        ]);
    }
}
```

- [ ] **Step 5: Write the table schema**

`app/Filament/Admin/Resources/Categories/Tables/CategoriesTable.php`:

```php
<?php

namespace App\Filament\Admin\Resources\Categories\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')->label('#')->sortable(),
                TextColumn::make('slug')->label('Слаг')->searchable(),
                TextColumn::make('name_en')->label('EN')->searchable(),
                TextColumn::make('name_ru')->label('RU')->searchable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
```

- [ ] **Step 6: Write the three page classes**

`app/Filament/Admin/Resources/Categories/Pages/ListCategories.php`:

```php
<?php

namespace App\Filament\Admin\Resources\Categories\Pages;

use App\Filament\Admin\Resources\Categories\CategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
```

`app/Filament/Admin/Resources/Categories/Pages/CreateCategory.php`:

```php
<?php

namespace App\Filament\Admin\Resources\Categories\Pages;

use App\Filament\Admin\Resources\Categories\CategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;
}
```

`app/Filament/Admin/Resources/Categories/Pages/EditCategory.php`:

```php
<?php

namespace App\Filament\Admin\Resources\Categories\Pages;

use App\Filament\Admin\Resources\Categories\CategoryResource;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;
}
```

- [ ] **Step 7: Run test to verify it passes**

```bash
docker compose exec workspace php artisan test --filter=CategoryResourceTest
```

Expected: 2 green tests. Filament auto-discovers resources under `app/Filament/Admin/Resources/`.

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Admin/Resources/Categories tests/Feature/Filament/CategoryResourceTest.php
git commit -m "Add Filament CategoryResource"
```

---

### Task 6: Add `category_id` + `is_featured` to `BattleForm`

**Files:**

- Modify: `app/Filament/Admin/Resources/Battles/Schemas/BattleForm.php`

- [ ] **Step 1: Add the two new form fields**

In `BattleForm::configure()`, after the `winning_side` `Select` and before `total_pool`, add:

```php
use App\Models\Category;
use Filament\Forms\Components\Toggle;
// ... existing imports ...

// Inside ->components([...]) array, insert:

Select::make('category_id')
    ->label('Категория')
    ->options(fn () => Category::query()->orderBy('sort_order')->pluck('name_en', 'id'))
    ->searchable()
    ->nullable(),
Toggle::make('is_featured')
    ->label('Показать в героя на главной')
    ->helperText('Если ничего не отмечено, героем становится активный баттл с самым большим банком.'),
```

- [ ] **Step 2: Smoke-test**

```bash
docker compose exec workspace php artisan test --filter=CategoryResourceTest
docker compose exec workspace php artisan test --filter=BattleScopesTest
```

Both suites should still pass.

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Admin/Resources/Battles/Schemas/BattleForm.php
git commit -m "Expose category and is_featured in Battle admin form"
```

---

## Phase 3 — Shared battle-row component

### Task 7: `<x-battle-row>` Blade component

**Files:**

- Create: `resources/views/components/battle-row.blade.php`

Used by Hot and All sections on the home page to render a battle as a compact row with avatars, title + pool, timer, and a VOTE link that navigates to the battle page (no inline voting — that's only on the Featured card).

- [ ] **Step 1: Write the component**

`resources/views/components/battle-row.blade.php`:

```blade
@props([
    'battle',
    'showVote' => true,
])

<a href="{{ route('battles.show', $battle) }}"
   class="grid grid-cols-[56px_minmax(0,1fr)_auto] items-center gap-3 rounded-xl border border-white/5 bg-white/[0.035] px-3 py-2.5 hover:bg-white/[0.06] transition">
    <span class="relative h-11 w-14 shrink-0">
        <span class="absolute inset-y-1 left-0 h-9 w-9 rounded-full bg-navy-700 border-2 border-navy-900 flex items-center justify-center text-[11px] font-bold text-white/90">
            {{ mb_strtoupper(mb_substr($battle->side_a_label, 0, 1)) }}
        </span>
        <span class="absolute inset-y-1 right-0 h-9 w-9 rounded-full bg-navy-700 border-2 border-navy-900 flex items-center justify-center text-[11px] font-bold text-white/90">
            {{ mb_strtoupper(mb_substr($battle->side_b_label, 0, 1)) }}
        </span>
    </span>

    <span class="min-w-0">
        <span class="block truncate text-sm text-white/90">{{ $battle->title }}</span>
        <span class="block mt-0.5 text-[11px] text-white/50">
            @if ($battle->status === \App\Models\Battle::STATUS_SETTLED)
                @if ($battle->winning_side === null)
                    {{ __('battle.refunded_tie') }}
                @else
                    {{ __('battle.winner') }}: {{ $battle->winning_side === \App\Models\Battle::SIDE_A ? $battle->side_a_label : $battle->side_b_label }}
                @endif
            @else
                {{ __('battle.pool') }}: {{ number_format((float) $battle->total_pool, 0) }}
            @endif
        </span>
    </span>

    <span class="text-right text-[11px] text-white/60">
        @if ($battle->status === \App\Models\Battle::STATUS_ACTIVE && $battle->closes_at !== null)
            ⏱ {{ $battle->closes_at->diffForHumans(['parts' => 1, 'short' => true]) }}
        @elseif ($battle->settled_at !== null)
            {{ $battle->settled_at->diffForHumans(['parts' => 1, 'short' => true]) }}
        @endif

        @if ($showVote && $battle->isOpenForVoting())
            <span class="mt-1.5 inline-block rounded-md bg-vote-blue-from/20 px-2.5 py-1 text-[11px] font-bold text-glow-cyan">
                {{ __('battle.vote') }}
            </span>
        @endif
    </span>
</a>
```

- [ ] **Step 2: Add the three new translation keys**

Modify `lang/en/battle.php` by appending:

```php
    'pool' => 'Pool',
    'vote' => 'VOTE',
    'winner' => 'Winner',
    'refunded_tie' => 'Tie — refunded',
```

Modify `lang/ru/battle.php` by appending:

```php
    'pool' => 'Банк',
    'vote' => 'ГОЛОС',
    'winner' => 'Победитель',
    'refunded_tie' => 'Ничья — возврат',
```

- [ ] **Step 3: Verify manually (no code test yet — tests land with `BattleIndexTest` in Task 13)**

```bash
docker compose exec workspace ./vendor/bin/pint resources/views/components/battle-row.blade.php lang/
```

- [ ] **Step 4: Commit**

```bash
git add resources/views/components/battle-row.blade.php lang/en/battle.php lang/ru/battle.php
git commit -m "Add shared <x-battle-row> component"
```

---

## Phase 4 — Home redesign

### Task 8: Rewrite `BattleIndex` Livewire component

**Files:**

- Modify: `app/Livewire/BattleIndex.php`

- [ ] **Step 1: Replace the component body**

Replace the contents of `app/Livewire/BattleIndex.php` with:

```php
<?php

namespace App\Livewire;

use App\Models\Battle;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class BattleIndex extends Component
{
    use WithPagination;

    #[Url(as: 'category')]
    public ?string $category = null;

    #[Url(as: 'finished')]
    public bool $finished = false;

    public function selectCategory(?string $slug): void
    {
        $this->category = $slug;
        $this->finished = false;
        $this->resetPage();
    }

    public function toggleFinished(): void
    {
        $this->finished = true;
        $this->category = null;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->category = null;
        $this->finished = false;
        $this->resetPage();
    }

    #[Layout('layouts.app')]
    public function render(): View
    {
        $featured = $this->finished ? null : Battle::resolveFeatured();

        $hot = $this->finished
            ? collect()
            : Battle::query()->active()
                ->with('category')
                ->when($featured, fn ($q, $f) => $q->whereKeyNot($f->id))
                ->orderByDesc('total_pool')
                ->limit(3)
                ->get();

        $categories = Category::query()->orderBy('sort_order')->get();

        $exclude = array_filter([$featured?->id, ...$hot->pluck('id')->all()]);

        $allQuery = $this->finished
            ? Battle::query()
                ->with('category')
                ->whereIn('status', [Battle::STATUS_SETTLED, Battle::STATUS_CLOSED])
                ->orderByDesc('settled_at')
            : Battle::query()->active()
                ->with('category')
                ->when(! empty($exclude), fn ($q) => $q->whereNotIn('id', $exclude))
                ->when($this->category, fn ($q, $slug) =>
                    $q->whereHas('category', fn ($qq) => $qq->where('slug', $slug))
                )
                ->orderByDesc('closes_at');

        $all = $allQuery->paginate(10);

        return view('livewire.battle-index', [
            'featured' => $featured,
            'hot' => $hot,
            'categories' => $categories,
            'all' => $all,
        ]);
    }
}
```

- [ ] **Step 2: Commit (view tests land in Task 13)**

```bash
git add app/Livewire/BattleIndex.php
git commit -m "Rewrite BattleIndex with category/finished filters"
```

---

### Task 9: `featured-card` partial

**Files:**

- Create: `resources/views/livewire/battle-index/featured-card.blade.php`

- [ ] **Step 1: Write the partial**

```blade
@php /** @var \App\Models\Battle $featured */ @endphp

<section class="mx-3 rounded-2xl border border-white/5 bg-white/[0.04] p-4 sm:p-5">
    <div class="text-[11px] uppercase tracking-wider text-white/55 mb-3">
        {{ __('battle.featured') }}
    </div>
    <div class="text-center mb-2">
        <h2 class="text-lg font-semibold text-white">{{ $featured->title }}</h2>
    </div>

    <livewire:battle-vote-widget :battle="$featured" :key="'featured-'.$featured->id" />
</section>
```

- [ ] **Step 2: Add the translation key**

Append to `lang/en/battle.php`: `'featured' => 'Featured battle',`
Append to `lang/ru/battle.php`: `'featured' => 'Баттл дня',`

- [ ] **Step 3: Commit**

```bash
git add resources/views/livewire/battle-index/featured-card.blade.php lang/en/battle.php lang/ru/battle.php
git commit -m "Add featured-card partial for BattleIndex"
```

---

### Task 10: `hot-rail` partial

**Files:**

- Create: `resources/views/livewire/battle-index/hot-rail.blade.php`

- [ ] **Step 1: Write the partial**

```blade
@php /** @var \Illuminate\Support\Collection<int, \App\Models\Battle> $hot */ @endphp

@if ($hot->isNotEmpty())
    <section class="mt-5">
        <div class="flex items-baseline justify-between px-3 mb-2">
            <div class="text-[11px] uppercase tracking-wider text-white/55">
                {{ __('battle.hot') }}
            </div>
            <button type="button"
                    wire:click="clearFilters"
                    x-on:click="$nextTick(() => document.getElementById('all-battles')?.scrollIntoView({behavior:'smooth'}))"
                    class="text-[11px] text-glow-cyan hover:underline">
                {{ __('battle.view_all') }} ›
            </button>
        </div>
        <div class="space-y-2 px-3">
            @foreach ($hot as $battle)
                <x-battle-row :battle="$battle" />
            @endforeach
        </div>
    </section>
@endif
```

- [ ] **Step 2: Add translation keys**

Append to `lang/en/battle.php`: `'hot' => 'Hot battles',` and `'view_all' => 'View all',`
Append to `lang/ru/battle.php`: `'hot' => 'Горячие',` and `'view_all' => 'Смотреть все',`

- [ ] **Step 3: Commit**

```bash
git add resources/views/livewire/battle-index/hot-rail.blade.php lang/en/battle.php lang/ru/battle.php
git commit -m "Add hot-rail partial for BattleIndex"
```

---

### Task 11: `category-chips` partial

**Files:**

- Create: `resources/views/livewire/battle-index/category-chips.blade.php`

- [ ] **Step 1: Write the partial**

```blade
@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Category> $categories */
@endphp

<nav class="mt-5 overflow-x-auto scrollbar-hide">
    <ul class="flex gap-2 px-3 min-w-max">
        <li>
            <button type="button"
                    wire:click="selectCategory(null)"
                    class="px-3 py-1.5 rounded-full border text-xs transition
                           {{ $category === null && ! $finished
                               ? 'bg-navy-800 border-white/30 text-white'
                               : 'border-white/10 text-white/70 hover:text-white' }}">
                {{ __('battle.all_chip') }}
            </button>
        </li>
        @foreach ($categories as $cat)
            <li>
                <button type="button"
                        wire:click="selectCategory('{{ $cat->slug }}')"
                        class="px-3 py-1.5 rounded-full border text-xs transition
                               {{ $category === $cat->slug
                                   ? 'bg-navy-800 border-white/30 text-white'
                                   : 'border-white/10 text-white/70 hover:text-white' }}">
                    {{ $cat->localized_name }}
                </button>
            </li>
        @endforeach
        <li>
            <button type="button"
                    wire:click="toggleFinished"
                    class="px-3 py-1.5 rounded-full border border-dashed text-xs transition
                           {{ $finished
                               ? 'bg-navy-800 border-white/40 text-white'
                               : 'border-white/20 text-white/60 hover:text-white' }}">
                {{ __('battle.finished_chip') }}
            </button>
        </li>
    </ul>
</nav>
```

- [ ] **Step 2: Add translation keys**

Append to `lang/en/battle.php`: `'all_chip' => 'All',` and `'finished_chip' => 'Finished',`
Append to `lang/ru/battle.php`: `'all_chip' => 'Все',` and `'finished_chip' => 'Завершённые',`

- [ ] **Step 3: Commit**

```bash
git add resources/views/livewire/battle-index/category-chips.blade.php lang/en/battle.php lang/ru/battle.php
git commit -m "Add category-chips partial for BattleIndex"
```

---

### Task 12: `all-list` partial

**Files:**

- Create: `resources/views/livewire/battle-index/all-list.blade.php`

- [ ] **Step 1: Write the partial**

```blade
@php
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $all */
@endphp

<section id="all-battles" class="mt-5">
    <div class="px-3 mb-2 text-[11px] uppercase tracking-wider text-white/55">
        {{ __('battle.all_battles') }}
    </div>

    @if ($all->total() === 0)
        <div class="mx-3 rounded-xl border border-dashed border-white/10 p-6 text-center text-sm text-white/50">
            @if ($finished)
                {{ __('battle.no_settled_battles') }}
            @elseif ($category !== null)
                {{ __('battle.no_battles_in_category') }}
            @else
                {{ __('battle.no_battles') }}
            @endif
        </div>
    @else
        <div class="space-y-2 px-3">
            @foreach ($all as $battle)
                <x-battle-row :battle="$battle" />
            @endforeach
        </div>

        @if ($all->hasMorePages())
            <div class="px-3 mt-3">
                <button type="button" wire:click="nextPage"
                        class="w-full rounded-xl border border-dashed border-white/10 py-3 text-sm text-white/60 hover:text-white">
                    {{ __('battle.load_more') }}
                </button>
            </div>
        @endif
    @endif
</section>
```

Note: `wire:click="nextPage"` works because `WithPagination` exposes the method. For Livewire's Tailwind paginator variant, `nextPage` is available on the component.

- [ ] **Step 2: Add translation keys**

Append to `lang/en/battle.php`:

```php
    'all_battles' => 'All battles',
    'load_more' => 'Load more',
    'no_battles' => 'No battles yet.',
    'no_battles_in_category' => 'Nothing in this category yet.',
    'no_settled_battles' => 'No settled battles yet.',
```

Append to `lang/ru/battle.php`:

```php
    'all_battles' => 'Все баттлы',
    'load_more' => 'Показать ещё',
    'no_battles' => 'Баттлов пока нет.',
    'no_battles_in_category' => 'В этой категории пока пусто.',
    'no_settled_battles' => 'Пока нет завершённых.',
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/livewire/battle-index/all-list.blade.php lang/en/battle.php lang/ru/battle.php
git commit -m "Add all-list partial for BattleIndex"
```

---

### Task 13: Rewrite `battle-index.blade.php` root view + tests

**Files:**

- Modify: `resources/views/livewire/battle-index.blade.php`
- Create: `tests/Feature/Livewire/BattleIndexTest.php`

- [ ] **Step 1: Write the failing test**

`tests/Feature/Livewire/BattleIndexTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\BattleIndex;
use App\Models\Battle;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BattleIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_render_surfaces_featured_hot_and_all(): void
    {
        $featured = Battle::factory()->create(['total_pool' => 500, 'is_featured' => true]);
        Battle::factory()->create(['total_pool' => 400]);
        Battle::factory()->create(['total_pool' => 300]);
        Battle::factory()->create(['total_pool' => 200]);
        Battle::factory()->create(['total_pool' => 100]);

        Livewire::test(BattleIndex::class)
            ->assertViewHas('featured', fn ($v) => $v !== null && $v->is($featured))
            ->assertViewHas('hot', fn ($hot) => $hot->count() === 3 && ! $hot->contains('id', $featured->id))
            ->assertViewHas('all', fn ($all) => $all->count() === 1);
    }

    public function test_selecting_a_category_filters_all_list(): void
    {
        $sports = Category::factory()->create(['slug' => 'sports']);
        $memes = Category::factory()->create(['slug' => 'memes']);

        Battle::factory()->create(['category_id' => $sports->id, 'total_pool' => 50]);
        Battle::factory()->create(['category_id' => $sports->id, 'total_pool' => 40]);
        Battle::factory()->create(['category_id' => $memes->id, 'total_pool' => 30]);

        // Also two in Hot top-3 to push the above into All:
        Battle::factory()->create(['total_pool' => 1000]);
        Battle::factory()->create(['total_pool' => 900]);
        Battle::factory()->create(['total_pool' => 800]);

        Livewire::test(BattleIndex::class)
            ->call('selectCategory', 'sports')
            ->assertViewHas('all', fn ($all) => $all->count() === 2);
    }

    public function test_toggle_finished_hides_featured_and_shows_settled(): void
    {
        Battle::factory()->create(['is_featured' => true, 'total_pool' => 100]);
        Battle::factory()->settled()->create();
        Battle::factory()->settled()->create();

        Livewire::test(BattleIndex::class)
            ->call('toggleFinished')
            ->assertViewHas('featured', null)
            ->assertViewHas('hot', fn ($hot) => $hot->isEmpty())
            ->assertViewHas('all', fn ($all) => $all->count() === 2);
    }

    public function test_empty_state_when_no_battles(): void
    {
        Livewire::test(BattleIndex::class)
            ->assertViewHas('featured', null)
            ->assertViewHas('hot', fn ($hot) => $hot->isEmpty())
            ->assertViewHas('all', fn ($all) => $all->count() === 0)
            ->assertSee(__('battle.no_battles'));
    }

    public function test_clear_filters_resets_category_and_finished(): void
    {
        Livewire::test(BattleIndex::class)
            ->set('category', 'sports')
            ->set('finished', true)
            ->call('clearFilters')
            ->assertSet('category', null)
            ->assertSet('finished', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker compose exec workspace php artisan test --filter=BattleIndexTest
```

Expected: rendering errors because the view still uses the old markup.

- [ ] **Step 3: Replace the root view**

Overwrite `resources/views/livewire/battle-index.blade.php`:

```blade
<div class="pb-6 lg:grid lg:grid-cols-[minmax(0,1fr)_320px] lg:gap-8 lg:max-w-7xl lg:mx-auto lg:px-6">
    <div class="max-w-xl mx-auto lg:mx-0">
        @if ($featured)
            @include('livewire.battle-index.featured-card', ['featured' => $featured])
        @endif

        @include('livewire.battle-index.hot-rail', ['hot' => $hot])

        @include('livewire.battle-index.category-chips', [
            'categories' => $categories,
            'category' => $category,
            'finished' => $finished,
        ])

        @include('livewire.battle-index.all-list', [
            'all' => $all,
            'category' => $category,
            'finished' => $finished,
        ])
    </div>

    <aside class="hidden lg:block">
        <livewire:sidebar-widgets />
    </aside>
</div>
```

- [ ] **Step 4: Run test to verify it passes**

```bash
docker compose exec workspace php artisan test --filter=BattleIndexTest
```

Expected: 5 green tests.

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/battle-index.blade.php tests/Feature/Livewire/BattleIndexTest.php
git commit -m "Wire BattleIndex partials into root view; add tests"
```

---

## Phase 5 — Layout shell

### Task 14: Icon Blade components

**Files:**

- Create: `resources/views/components/icon/home.blade.php`
- Create: `resources/views/components/icon/trophy.blade.php`
- Create: `resources/views/components/icon/chart.blade.php`
- Create: `resources/views/components/icon/user.blade.php`
- Create: `resources/views/components/icon/search.blade.php`

- [ ] **Step 1: Write each SVG component**

`resources/views/components/icon/home.blade.php`:

```blade
<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
     stroke-width="1.5" stroke="currentColor" {{ $attributes->merge(['class' => 'h-5 w-5']) }}>
    <path stroke-linecap="round" stroke-linejoin="round"
          d="M2.25 12 12 3.75 21.75 12M4.5 9.75v9.75h4.5v-6h6v6h4.5V9.75" />
</svg>
```

`resources/views/components/icon/trophy.blade.php`:

```blade
<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
     stroke-width="1.5" stroke="currentColor" {{ $attributes->merge(['class' => 'h-5 w-5']) }}>
    <path stroke-linecap="round" stroke-linejoin="round"
          d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
</svg>
```

`resources/views/components/icon/chart.blade.php`:

```blade
<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
     stroke-width="1.5" stroke="currentColor" {{ $attributes->merge(['class' => 'h-5 w-5']) }}>
    <path stroke-linecap="round" stroke-linejoin="round"
          d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
</svg>
```

`resources/views/components/icon/user.blade.php`:

```blade
<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
     stroke-width="1.5" stroke="currentColor" {{ $attributes->merge(['class' => 'h-5 w-5']) }}>
    <path stroke-linecap="round" stroke-linejoin="round"
          d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
</svg>
```

`resources/views/components/icon/search.blade.php`:

```blade
<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
     stroke-width="1.5" stroke="currentColor" {{ $attributes->merge(['class' => 'h-5 w-5']) }}>
    <path stroke-linecap="round" stroke-linejoin="round"
          d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
</svg>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/components/icon
git commit -m "Add navigation icon components"
```

---

### Task 15: `bottom-nav.blade.php` partial

**Files:**

- Create: `resources/views/layouts/bottom-nav.blade.php`

- [ ] **Step 1: Write the partial**

```blade
@php
    $tabs = [
        [
            'route' => 'home',
            'match' => ['home', 'battles.*'],
            'label' => __('nav.home'),
            'icon'  => 'home',
        ],
        [
            'route' => 'leaderboard',
            'match' => ['leaderboard'],
            'label' => __('nav.leaderboard'),
            'icon'  => 'trophy',
        ],
        [
            'route' => 'my-bets',
            'match' => ['my-bets'],
            'label' => __('nav.my_bets'),
            'icon'  => 'chart',
        ],
        [
            'route' => 'profile.edit',
            'match' => ['profile.*'],
            'label' => __('nav.profile'),
            'icon'  => 'user',
        ],
    ];
@endphp

<nav class="fixed bottom-0 inset-x-0 z-40 sm:hidden bg-navy-900/95 backdrop-blur border-t border-white/5 pb-[env(safe-area-inset-bottom)]">
    <ul class="grid grid-cols-4">
        @foreach ($tabs as $tab)
            <li>
                <a href="{{ route($tab['route']) }}"
                   class="flex flex-col items-center gap-1 py-2.5 text-[10px] {{ request()->routeIs(...$tab['match']) ? 'text-white' : 'text-white/55 hover:text-white' }}">
                    <x-dynamic-component :component="'icon.'.$tab['icon']" class="h-5 w-5" />
                    <span>{{ $tab['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
```

- [ ] **Step 2: Add translation keys**

Append to `lang/en/nav.php`:

```php
    'home' => 'Home',
    'leaderboard' => 'Leaderboard',
    'my_bets' => 'My Bets',
```

Append to `lang/ru/nav.php`:

```php
    'home' => 'Главная',
    'leaderboard' => 'Лидеры',
    'my_bets' => 'Мои ставки',
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/layouts/bottom-nav.blade.php lang/en/nav.php lang/ru/nav.php
git commit -m "Add mobile bottom-nav partial"
```

---

### Task 16: Wire `bottom-nav` into `app.blade.php`

**Files:**

- Modify: `resources/views/layouts/app.blade.php`

- [ ] **Step 1: Insert bottom nav + adjust main padding**

Modify `resources/views/layouts/app.blade.php` so that:

1. `<main>` gains the class `pb-20 sm:pb-0` (space for the fixed bottom nav on mobile).
2. Immediately after `</main>`, include `@include('layouts.bottom-nav')`.

The final body contents look like:

```blade
<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        @include('layouts.navigation')

        @isset($header)
            <header class="bg-white dark:bg-gray-800 shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <main class="pb-20 sm:pb-0">
            {{ $slot }}
        </main>

        @include('layouts.bottom-nav')
    </div>
</body>
```

- [ ] **Step 2: Smoke-test: app still boots**

```bash
docker compose exec workspace php artisan test --filter=BattleIndexTest
```

Expected: 5 green tests. The view now wraps in the new layout.

- [ ] **Step 3: Commit**

```bash
git add resources/views/layouts/app.blade.php
git commit -m "Include bottom-nav in app layout"
```

---

### Task 17: Mobile hamburger removal + search button in `navigation.blade.php`

**Files:**

- Modify: `resources/views/layouts/navigation.blade.php`

- [ ] **Step 1: Remove the mobile hamburger and its collapsible panel**

In `resources/views/layouts/navigation.blade.php`:

- Delete the entire `{{-- Hamburger (mobile) --}}` block (the `<div class="-me-2 flex items-center sm:hidden">` with the hamburger button).
- Delete the entire `{{-- Responsive navigation menu --}}` block (the `<div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden border-t border-white/5">...</div>`).
- Remove the `x-data="{ open: false }"` and the `<nav>` wrapper's `x-data` no longer needs Alpine state — change the opening tag to `<nav class="bg-navy-900 border-b border-white/5 text-white">`.

- [ ] **Step 2: Add a mobile-safe balance pill and login link**

Since the hamburger is gone, mobile users need to see the balance/login control inline. Replace the right-hand `<div class="hidden sm:flex items-center gap-3">` with a block that renders on all sizes:

```blade
<div class="flex items-center gap-2 sm:gap-3">
    <button type="button"
            x-on:click="$dispatch('open-search')"
            class="p-2 rounded-full text-white/70 hover:text-white hover:bg-white/5 transition"
            aria-label="{{ __('search.open') }}">
        <x-icon.search class="h-5 w-5" />
    </button>

    <div class="hidden sm:flex items-center gap-3">
        <livewire:locale-switcher />
    </div>

    @auth
        <div class="hidden sm:flex items-center gap-2">
            {{-- existing notifications + messages buttons remain here, unchanged --}}
            {{-- copy them verbatim from the previous markup --}}
        </div>

        <x-dropdown align="right" width="48" contentClasses="py-1 bg-navy-800 text-white/90">
            <x-slot name="trigger">
                <button class="flex items-center gap-2 rounded-full bg-white/5 hover:bg-white/10 pl-1 pr-3 py-1 transition">
                    <span class="h-7 w-7 rounded-full bg-navy-700 flex items-center justify-center text-xs font-semibold">
                        {{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}
                    </span>
                    <span class="text-xs font-semibold"
                          x-data="{ balance: {{ (int) Auth::user()->balance }} }"
                          x-on:balance-updated.window="balance = $event.detail.balance">
                        <span class="text-white"
                              x-text="new Intl.NumberFormat().format(balance)">{{ number_format((float) Auth::user()->balance, 0) }}</span>
                        <span class="text-white/50 font-normal ml-1 hidden sm:inline">{{ __('sidebar.tokens') }}</span>
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                         class="h-4 w-4 text-white/50 hidden sm:inline">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </x-slot>

            <x-slot name="content">
                <x-dropdown-link :href="route('profile.edit')"
                                 class="!text-white/80 hover:!bg-white/10 hover:!text-white">
                    {{ __('nav.profile') }}
                </x-dropdown-link>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-dropdown-link :href="route('logout')"
                                     class="!text-white/80 hover:!bg-white/10 hover:!text-white"
                                     onclick="event.preventDefault(); this.closest('form').submit();">
                        {{ __('nav.logout') }}
                    </x-dropdown-link>
                </form>
            </x-slot>
        </x-dropdown>
    @endauth

    @guest
        <a href="{{ route('login') }}"
           class="text-sm text-white/80 hover:text-white transition">{{ __('nav.login') }}</a>
        <a href="{{ route('register') }}"
           class="hidden sm:inline-block text-sm rounded-md bg-white/10 hover:bg-white/20 px-3 py-1.5 transition">
            {{ __('nav.register') }}
        </a>
    @endguest
</div>
```

Also in the desktop primary menu block (`<div class="hidden sm:flex items-center gap-6 text-sm">`), keep the existing Battles + Referrals links untouched.

- [ ] **Step 3: Add `search.open` translation**

Append to `lang/en/search.php` (create the file if it does not exist):

```php
<?php

return [
    'open' => 'Open search',
];
```

Append to `lang/ru/search.php`:

```php
<?php

return [
    'open' => 'Открыть поиск',
];
```

- [ ] **Step 4: Smoke-test**

```bash
docker compose exec workspace php artisan test --filter=BattleIndexTest
```

Expected: still 5 green tests.

- [ ] **Step 5: Commit**

```bash
git add resources/views/layouts/navigation.blade.php lang/en/search.php lang/ru/search.php
git commit -m "Drop mobile hamburger; add search button to navbar"
```

---

### Task 18: `BottomNavTest` — presence and active-tab highlight

**Files:**

- Create: `tests/Feature/Http/BottomNavTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BottomNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_bottom_nav_is_rendered_on_home(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(__('nav.home'))
            ->assertSee(__('nav.leaderboard'))
            ->assertSee(__('nav.my_bets'))
            ->assertSee(__('nav.profile'));
    }

    public function test_guest_clicking_my_bets_is_redirected_to_login(): void
    {
        $this->get('/my-bets')->assertRedirect('/login');
    }

    public function test_authed_user_can_reach_my_bets(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/my-bets')->assertOk();
    }
}
```

- [ ] **Step 2: Run test**

```bash
docker compose exec workspace php artisan test --filter=BottomNavTest
```

Expected: first assertion (home) passes; `/my-bets` assertions fail — the route does not exist yet. That's OK, they pass after Task 26.

- [ ] **Step 3: Defer my-bets assertions with `markTestIncomplete` for now**

Temporarily wrap the two `/my-bets` tests with a `$this->markTestIncomplete('Awaiting /my-bets route in Task 26.');` at the top of each. Remove the `markTestIncomplete` calls in Task 26 step 6.

- [ ] **Step 4: Re-run**

```bash
docker compose exec workspace php artisan test --filter=BottomNavTest
```

Expected: 1 pass, 2 incomplete.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Http/BottomNavTest.php
git commit -m "Add BottomNavTest (my-bets cases incomplete until Task 26)"
```

---

## Phase 6 — Search overlay

### Task 19: `SearchOverlay` Livewire component

**Files:**

- Create: `app/Livewire/SearchOverlay.php`

- [ ] **Step 1: Write the component**

```php
<?php

namespace App\Livewire;

use App\Models\Battle;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

class SearchOverlay extends Component
{
    public string $query = '';

    public function render(): View
    {
        $trimmed = trim($this->query);
        $results = mb_strlen($trimmed) < 2
            ? new Collection()
            : $this->search($trimmed);

        return view('livewire.search-overlay', [
            'results' => $results,
            'queryLength' => mb_strlen($trimmed),
        ]);
    }

    /**
     * @return Collection<int, Battle>
     */
    private function search(string $trimmed): Collection
    {
        $needle = '%'.mb_strtolower($trimmed).'%';

        return Battle::query()
            ->with('category')
            ->where(function (Builder $w) use ($needle) {
                $w->whereRaw('LOWER(title) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(side_a_label) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(side_b_label) LIKE ?', [$needle]);
            })
            ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('total_pool')
            ->limit(15)
            ->get();
    }
}
```

- [ ] **Step 2: Commit (view + test land in Tasks 20/21)**

```bash
git add app/Livewire/SearchOverlay.php
git commit -m "Add SearchOverlay component with case-insensitive title/side search"
```

---

### Task 20: `search-overlay` view + top-bar wiring

**Files:**

- Create: `resources/views/livewire/search-overlay.blade.php`
- Modify: `resources/views/layouts/app.blade.php`

- [ ] **Step 1: Write the view**

```blade
<div x-data="{ open: false }"
     x-on:open-search.window="open = true; $nextTick(() => $refs.input?.focus())"
     x-on:keydown.escape.window="open = false"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 bg-navy-900/95 backdrop-blur flex flex-col">

    <div class="flex items-center gap-3 px-4 py-3 border-b border-white/5">
        <x-icon.search class="h-5 w-5 text-white/60" />
        <input type="text"
               x-ref="input"
               wire:model.live.debounce.300ms="query"
               placeholder="{{ __('search.placeholder') }}"
               class="flex-1 bg-transparent border-0 text-white placeholder-white/40 focus:outline-none focus:ring-0" />
        <button type="button"
                x-on:click="open = false"
                class="text-sm text-glow-cyan hover:underline">
            {{ __('search.cancel') }}
        </button>
    </div>

    <div class="flex-1 overflow-y-auto">
        @if ($queryLength === 0)
            <div class="p-8 text-center text-sm text-white/45">
                {{ __('search.prompt') }}
            </div>
        @elseif ($queryLength < 2)
            <div class="p-8 text-center text-sm text-white/45">
                {{ __('search.min_chars') }}
            </div>
        @elseif ($results->isEmpty())
            <div class="p-8 text-center text-sm text-white/45">
                {{ __('search.no_results') }}
            </div>
        @else
            <ul class="divide-y divide-white/5">
                @foreach ($results as $battle)
                    <li>
                        <a href="{{ route('battles.show', $battle) }}"
                           x-on:click="open = false"
                           class="flex items-center gap-3 px-4 py-3 hover:bg-white/[0.03]">
                            <span class="h-8 w-10 rounded-md bg-gradient-to-r from-vote-blue-from to-vote-purple-to"></span>
                            <span class="flex-1 min-w-0">
                                <span class="block truncate text-sm text-white/90">{{ $battle->title }}</span>
                                <span class="block text-[11px] text-white/50">
                                    @if ($battle->category) {{ $battle->category->localized_name }} · @endif
                                    {{ __('battle.status_'.$battle->status) }}
                                </span>
                            </span>
                            <span class="shrink-0 text-xs text-white/60">
                                {{ number_format((float) $battle->total_pool, 0) }}
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
```

- [ ] **Step 2: Insert the overlay once in the layout**

Modify `resources/views/layouts/app.blade.php` — add `<livewire:search-overlay />` as the last child of `<body>`, after the closing `</div>` of the `.min-h-screen` wrapper:

```blade
<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        {{-- ... as before ... --}}
    </div>

    <livewire:search-overlay />
</body>
```

- [ ] **Step 3: Add translation keys**

Append to `lang/en/search.php`:

```php
    'placeholder' => 'Search battles…',
    'min_chars'   => 'Type at least 2 characters.',
    'no_results'  => 'No battles match your search.',
    'prompt'      => 'Search battles by title or participant.',
    'cancel'      => 'Cancel',
```

Append to `lang/ru/search.php`:

```php
    'placeholder' => 'Искать баттлы…',
    'min_chars'   => 'Минимум 2 символа.',
    'no_results'  => 'Ничего не найдено.',
    'prompt'      => 'Поиск по названию или участнику.',
    'cancel'      => 'Отмена',
```

Append to `lang/en/battle.php`: `'status_active' => 'active',`, `'status_draft' => 'draft',`, `'status_closed' => 'closed',`, `'status_settled' => 'settled',`
Append to `lang/ru/battle.php`: `'status_active' => 'активен',`, `'status_draft' => 'черновик',`, `'status_closed' => 'закрыт',`, `'status_settled' => 'завершён',`

- [ ] **Step 4: Commit**

```bash
git add resources/views/livewire/search-overlay.blade.php \
        resources/views/layouts/app.blade.php \
        lang/en/search.php lang/ru/search.php \
        lang/en/battle.php lang/ru/battle.php
git commit -m "Wire SearchOverlay into layout with i18n keys"
```

---

### Task 21: `SearchOverlayTest`

**Files:**

- Create: `tests/Feature/Livewire/SearchOverlayTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\SearchOverlay;
use App\Models\Battle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SearchOverlayTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_query_returns_no_results(): void
    {
        Battle::factory()->create(['title' => 'Messi vs Ronaldo']);

        Livewire::test(SearchOverlay::class)
            ->assertViewHas('results', fn ($r) => $r->isEmpty());
    }

    public function test_single_char_query_returns_no_results(): void
    {
        Battle::factory()->create(['title' => 'Messi vs Ronaldo']);

        Livewire::test(SearchOverlay::class)
            ->set('query', 'M')
            ->assertViewHas('results', fn ($r) => $r->isEmpty());
    }

    public function test_matches_title_case_insensitively(): void
    {
        $match = Battle::factory()->create(['title' => 'Messi vs Ronaldo']);
        Battle::factory()->create(['title' => 'Marvel vs DC']);

        Livewire::test(SearchOverlay::class)
            ->set('query', 'ronaldo')
            ->assertViewHas('results', fn ($r) => $r->count() === 1 && $r->first()->is($match));
    }

    public function test_matches_side_labels(): void
    {
        $match = Battle::factory()->create([
            'title' => 'Clash',
            'side_a_label' => 'Ronaldo',
            'side_b_label' => 'Messi',
        ]);

        Livewire::test(SearchOverlay::class)
            ->set('query', 'ronald')
            ->assertViewHas('results', fn ($r) => $r->count() === 1 && $r->first()->is($match));
    }

    public function test_active_battles_rank_before_settled_on_same_match(): void
    {
        $active = Battle::factory()->create(['title' => 'Cats vs Dogs', 'total_pool' => 10]);
        $settled = Battle::factory()->settled()->create(['title' => 'Cats vs Birds', 'total_pool' => 9999]);

        Livewire::test(SearchOverlay::class)
            ->set('query', 'cats')
            ->assertViewHas('results', function ($r) use ($active, $settled) {
                return $r->count() === 2 && $r->first()->is($active) && $r->last()->is($settled);
            });
    }

    public function test_results_are_capped_at_15(): void
    {
        Battle::factory()->count(20)->create(['title' => 'abc battle']);

        Livewire::test(SearchOverlay::class)
            ->set('query', 'abc')
            ->assertViewHas('results', fn ($r) => $r->count() === 15);
    }
}
```

- [ ] **Step 2: Run**

```bash
docker compose exec workspace php artisan test --filter=SearchOverlayTest
```

Expected: 6 green tests.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Livewire/SearchOverlayTest.php
git commit -m "Test SearchOverlay: min chars, match fields, ordering, cap"
```

---

## Phase 7 — Leaderboard

### Task 22: `Leaderboard` Livewire component

**Files:**

- Create: `app/Livewire/Leaderboard.php`

- [ ] **Step 1: Write the component**

```php
<?php

namespace App\Livewire;

use App\Models\Battle;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Leaderboard extends Component
{
    #[Layout('layouts.app')]
    public function render(): View
    {
        $winningsSubquery = $this->winningsSubquery();

        $rows = User::query()
            ->select('users.id', 'users.name')
            ->leftJoinSub($winningsSubquery, 'w', fn ($j) => $j->on('w.user_id', '=', 'users.id'))
            ->selectRaw('COALESCE(w.total_winnings, 0) AS total_winnings')
            ->orderByDesc('total_winnings')
            ->orderBy('users.id')
            ->limit(100)
            ->get();

        $me = null;
        if (Auth::check() && ! $rows->contains('id', Auth::id())) {
            $me = $this->buildSelfRow($winningsSubquery);
        }

        return view('livewire.leaderboard', [
            'rows' => $rows,
            'me' => $me,
        ]);
    }

    private function winningsSubquery(): QueryBuilder
    {
        return Vote::query()
            ->join('battles', 'battles.id', '=', 'votes.battle_id')
            ->where('battles.status', Battle::STATUS_SETTLED)
            ->whereNotNull('battles.winning_side')
            ->whereColumn('votes.side', 'battles.winning_side')
            ->selectRaw('votes.user_id, COALESCE(SUM(votes.payout), 0) AS total_winnings')
            ->groupBy('votes.user_id')
            ->toBase();
    }

    /**
     * @return array{rank:int,total_winnings:float}
     */
    private function buildSelfRow(QueryBuilder $winningsSubquery): array
    {
        $mine = (float) Vote::query()
            ->join('battles', 'battles.id', '=', 'votes.battle_id')
            ->where('votes.user_id', Auth::id())
            ->where('battles.status', Battle::STATUS_SETTLED)
            ->whereNotNull('battles.winning_side')
            ->whereColumn('votes.side', 'battles.winning_side')
            ->sum('votes.payout');

        $ahead = (int) DB::query()
            ->fromSub($winningsSubquery, 'w')
            ->where('total_winnings', '>', $mine)
            ->count();

        return [
            'rank' => $ahead + 1,
            'total_winnings' => round($mine, 2),
        ];
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Livewire/Leaderboard.php
git commit -m "Add Leaderboard component with winnings aggregate"
```

---

### Task 23: `leaderboard` view + route

**Files:**

- Create: `resources/views/livewire/leaderboard.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write the view**

```blade
<div class="max-w-xl mx-auto pt-4 pb-6">
    <header class="px-4 mb-4">
        <h1 class="text-xl font-semibold text-white">{{ __('leaderboard.title') }}</h1>
        <p class="text-xs text-white/50 mt-1">{{ __('leaderboard.top_winners_all_time') }}</p>
    </header>

    @if ($rows->isEmpty() || $rows->every(fn ($r) => (float) $r->total_winnings === 0.0))
        <div class="mx-4 rounded-xl border border-dashed border-white/10 p-6 text-center text-sm text-white/50">
            {{ __('leaderboard.empty') }}
        </div>
    @else
        <ul class="divide-y divide-white/5">
            @foreach ($rows as $index => $row)
                @php $rank = $index + 1; @endphp
                <li class="grid grid-cols-[32px_32px_1fr_auto] items-center gap-3 px-4 py-2.5
                           {{ auth()->check() && auth()->id() === $row->id ? 'bg-glow-cyan/10' : '' }}">
                    <span class="text-sm font-bold text-center {{ $rank <= 3 ? 'text-gold-500' : 'text-white/45' }}">
                        {{ $rank }}
                    </span>
                    <span class="h-8 w-8 rounded-full bg-navy-700 flex items-center justify-center text-[11px] font-semibold text-white">
                        {{ mb_strtoupper(mb_substr($row->name, 0, 1)) }}
                    </span>
                    <span class="min-w-0 truncate text-sm text-white/90">{{ $row->name }}</span>
                    <span class="text-xs text-white/70">{{ number_format((float) $row->total_winnings, 0) }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($me)
        <div class="mt-4 px-4">
            <div class="text-[11px] uppercase tracking-wider text-white/55 mb-2">
                {{ __('leaderboard.your_position') }}
            </div>
            <div class="grid grid-cols-[32px_32px_1fr_auto] items-center gap-3 rounded-xl bg-glow-cyan/10 border border-glow-cyan/20 px-3 py-2.5">
                <span class="text-sm font-bold text-center text-white/80">{{ $me['rank'] }}</span>
                <span class="h-8 w-8 rounded-full bg-navy-700 flex items-center justify-center text-[11px] font-semibold text-white">
                    {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                </span>
                <span class="min-w-0 truncate text-sm text-white/90">{{ auth()->user()->name }}</span>
                <span class="text-xs text-white/70">{{ number_format($me['total_winnings'], 0) }}</span>
            </div>
        </div>
    @endif
</div>
```

- [ ] **Step 2: Register the route**

Modify `routes/web.php`. Add `use App\Livewire\Leaderboard;` at the top, then add below the existing `Route::get('/battles/{battle:slug}', ...)` line:

```php
Route::get('/leaderboard', Leaderboard::class)->name('leaderboard');
```

- [ ] **Step 3: Translations**

Create `lang/en/leaderboard.php`:

```php
<?php

return [
    'title' => 'Leaderboard',
    'top_winners_all_time' => 'Top winners — all time',
    'your_position' => 'Your position',
    'empty' => 'No winners yet. Be the first!',
];
```

Create `lang/ru/leaderboard.php`:

```php
<?php

return [
    'title' => 'Лидеры',
    'top_winners_all_time' => 'Лучшие по выигрышам — всё время',
    'your_position' => 'Ваша позиция',
    'empty' => 'Пока нет выигрышей. Будь первым!',
];
```

- [ ] **Step 4: Commit**

```bash
git add resources/views/livewire/leaderboard.blade.php routes/web.php \
        lang/en/leaderboard.php lang/ru/leaderboard.php
git commit -m "Add /leaderboard route and view"
```

---

### Task 24: `LeaderboardTest`

**Files:**

- Create: `tests/Feature/Livewire/LeaderboardTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Leaderboard;
use App\Models\Battle;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranks_users_by_sum_of_winning_payouts(): void
    {
        [$alice, $bob, $carol] = User::factory()->count(3)->create()->all();

        $battle = Battle::factory()->settled(Battle::SIDE_A)->create();

        Vote::factory()->create([
            'user_id' => $alice->id, 'battle_id' => $battle->id,
            'side' => 'A', 'amount' => 100, 'weight' => 100, 'payout' => 500,
        ]);
        Vote::factory()->create([
            'user_id' => $bob->id, 'battle_id' => $battle->id,
            'side' => 'A', 'amount' => 50, 'weight' => 50, 'payout' => 250,
        ]);
        Vote::factory()->create([
            'user_id' => $carol->id, 'battle_id' => $battle->id,
            'side' => 'B', 'amount' => 80, 'weight' => 80, 'payout' => null,
        ]);

        Livewire::test(Leaderboard::class)
            ->assertViewHas('rows', function ($rows) use ($alice, $bob, $carol) {
                $map = $rows->keyBy('id');
                return (float) $map[$alice->id]->total_winnings === 500.0
                    && (float) $map[$bob->id]->total_winnings === 250.0
                    && (float) $map[$carol->id]->total_winnings === 0.0;
            });
    }

    public function test_refund_votes_do_not_count_as_winnings(): void
    {
        $user = User::factory()->create();

        $tiedBattle = Battle::factory()->create([
            'status' => Battle::STATUS_SETTLED,
            'winning_side' => null,
            'settled_at' => now(),
        ]);

        Vote::factory()->create([
            'user_id' => $user->id, 'battle_id' => $tiedBattle->id,
            'side' => 'A', 'amount' => 100, 'weight' => 100, 'payout' => 100,
        ]);

        Livewire::test(Leaderboard::class)
            ->assertViewHas('rows', function ($rows) use ($user) {
                return (float) $rows->firstWhere('id', $user->id)->total_winnings === 0.0;
            });
    }

    public function test_ties_break_on_user_id(): void
    {
        $battle = Battle::factory()->settled(Battle::SIDE_A)->create();

        foreach (range(1, 3) as $_) {
            $u = User::factory()->create();
            Vote::factory()->create([
                'user_id' => $u->id, 'battle_id' => $battle->id,
                'side' => 'A', 'amount' => 10, 'weight' => 10, 'payout' => 10,
            ]);
        }

        $component = Livewire::test(Leaderboard::class);
        $ids = $component->viewData('rows')->pluck('id')->all();
        sort($ids);
        $this->assertSame($ids, $component->viewData('rows')->pluck('id')->all());
    }

    public function test_authed_user_outside_top_100_gets_your_position_row(): void
    {
        $battle = Battle::factory()->settled(Battle::SIDE_A)->create();

        // 100 users ahead
        for ($i = 0; $i < 100; $i++) {
            $u = User::factory()->create();
            Vote::factory()->create([
                'user_id' => $u->id, 'battle_id' => $battle->id,
                'side' => 'A', 'amount' => 10, 'weight' => 10, 'payout' => 1000,
            ]);
        }

        $me = User::factory()->create();
        Vote::factory()->create([
            'user_id' => $me->id, 'battle_id' => $battle->id,
            'side' => 'A', 'amount' => 1, 'weight' => 1, 'payout' => 1,
        ]);

        Livewire::actingAs($me)
            ->test(Leaderboard::class)
            ->assertViewHas('me', fn ($me) => $me !== null && $me['rank'] === 101 && (float) $me['total_winnings'] === 1.0);
    }

    public function test_guest_sees_no_your_position_row(): void
    {
        Livewire::test(Leaderboard::class)->assertViewHas('me', null);
    }
}
```

- [ ] **Step 2: Run**

```bash
docker compose exec workspace php artisan test --filter=LeaderboardTest
```

Expected: 5 green tests.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Livewire/LeaderboardTest.php
git commit -m "Test Leaderboard: aggregation, refunds, ties, pinned row"
```

---

### Task 25: Sync `SidebarWidgets::topPlayers` to same aggregate

**Files:**

- Modify: `app/Livewire/SidebarWidgets.php`
- Modify: `resources/views/livewire/sidebar-widgets.blade.php`
- Create: `tests/Feature/Livewire/SidebarWidgetsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\SidebarWidgets;
use App\Models\Battle;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SidebarWidgetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_players_ranked_by_winnings_not_balance(): void
    {
        [$rich, $winner] = User::factory()->count(2)->create()->all();
        $rich->update(['balance' => 100000]);
        $winner->update(['balance' => 100]);

        $battle = Battle::factory()->settled(Battle::SIDE_A)->create();
        Vote::factory()->create([
            'user_id' => $winner->id, 'battle_id' => $battle->id,
            'side' => 'A', 'amount' => 50, 'weight' => 50, 'payout' => 750,
        ]);

        Livewire::test(SidebarWidgets::class)
            ->assertViewHas('topPlayers', function ($players) use ($winner, $rich) {
                return $players->first()->id === $winner->id
                    && (float) $players->first()->total_winnings === 750.0
                    && (float) $players->firstWhere('id', $rich->id)?->total_winnings === 0.0;
            });
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker compose exec workspace php artisan test --filter=SidebarWidgetsTest
```

Expected: fails — `total_winnings` isn't on the rows.

- [ ] **Step 3: Replace the `topPlayers` query**

In `app/Livewire/SidebarWidgets.php`, replace the `$topPlayers = ...` block inside `render()` with:

```php
use Illuminate\Database\Eloquent\Builder;
// (import already present for User — verify)

$winningsSubquery = Vote::query()
    ->join('battles', 'battles.id', '=', 'votes.battle_id')
    ->where('battles.status', Battle::STATUS_SETTLED)
    ->whereNotNull('battles.winning_side')
    ->whereColumn('votes.side', 'battles.winning_side')
    ->selectRaw('votes.user_id, COALESCE(SUM(votes.payout), 0) AS total_winnings')
    ->groupBy('votes.user_id')
    ->toBase();

$topPlayers = User::query()
    ->select('users.id', 'users.name')
    ->leftJoinSub($winningsSubquery, 'w', fn ($j) => $j->on('w.user_id', '=', 'users.id'))
    ->selectRaw('COALESCE(w.total_winnings, 0) AS total_winnings')
    ->orderByDesc('total_winnings')
    ->orderBy('users.id')
    ->limit(3)
    ->get();
```

Add `use App\Models\Vote;` if it's not already imported in the file.

- [ ] **Step 4: Update the view**

In `resources/views/livewire/sidebar-widgets.blade.php`, the "Top Players" section currently renders `$p->balance`. Replace with `$p->total_winnings`:

```blade
<span class="shrink-0 text-xs text-white/60">
    {{ number_format((float) $p->total_winnings, 0) }}
    <span class="text-white/40">{{ __('sidebar.tokens') }}</span>
</span>
```

- [ ] **Step 5: Run test to verify it passes**

```bash
docker compose exec workspace php artisan test --filter=SidebarWidgetsTest
```

Expected: 1 green test. Also re-run the full suite touched so far:

```bash
docker compose exec workspace php artisan test --filter='BattleIndexTest|LeaderboardTest'
```

Both should still pass.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/SidebarWidgets.php \
        resources/views/livewire/sidebar-widgets.blade.php \
        tests/Feature/Livewire/SidebarWidgetsTest.php
git commit -m "Sync sidebar Top Players to total winnings"
```

---

## Phase 8 — My Bets

### Task 26: `MyBets` component + route + view

**Files:**

- Create: `app/Livewire/MyBets.php`
- Create: `resources/views/livewire/my-bets.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write the component**

```php
<?php

namespace App\Livewire;

use App\Models\Battle;
use App\Models\Vote;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class MyBets extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'active';

    public function selectTab(string $tab): void
    {
        $this->tab = in_array($tab, ['active', 'settled'], true) ? $tab : 'active';
        $this->resetPage();
    }

    #[Layout('layouts.app')]
    public function render(): View
    {
        $query = Vote::query()
            ->where('user_id', Auth::id())
            ->with(['battle:id,title,slug,status,side_a_label,side_b_label,winning_side,total_pool,closes_at,settled_at']);

        if ($this->tab === 'active') {
            $query->whereHas('battle', fn ($b) => $b->where('status', Battle::STATUS_ACTIVE));
        } else {
            $query->whereHas('battle', fn ($b) => $b->whereIn('status', [Battle::STATUS_SETTLED, Battle::STATUS_CLOSED]));
        }

        $votes = $query->latest()->paginate(20);

        return view('livewire.my-bets', [
            'votes' => $votes,
        ]);
    }

    public function statusFor(Vote $vote): string
    {
        $battle = $vote->battle;

        if ($battle->status === Battle::STATUS_ACTIVE) {
            return 'active';
        }

        if ($battle->winning_side === null) {
            return 'refund';
        }

        return $vote->side === $battle->winning_side ? 'won' : 'lost';
    }

    public function netAmountFor(Vote $vote): float
    {
        return match ($this->statusFor($vote)) {
            'won', 'refund' => (float) ($vote->payout ?? 0),
            'lost' => -(float) $vote->amount,
            default => 0.0,
        };
    }
}
```

- [ ] **Step 2: Write the view**

`resources/views/livewire/my-bets.blade.php`:

```blade
<div class="max-w-xl mx-auto pt-4 pb-6">
    <header class="px-4 mb-3 flex items-center gap-2">
        <x-icon.chart class="h-5 w-5 text-white/70" />
        <h1 class="text-xl font-semibold text-white">{{ __('my_bets.title') }}</h1>
    </header>

    <div class="mx-3 grid grid-cols-2 rounded-xl overflow-hidden bg-white/[0.04] border border-white/5 mb-3">
        <button type="button"
                wire:click="selectTab('active')"
                class="py-2 text-xs text-center
                       {{ $tab === 'active' ? 'bg-navy-800 text-white' : 'text-white/60 hover:text-white' }}">
            {{ __('my_bets.tab_active') }}
        </button>
        <button type="button"
                wire:click="selectTab('settled')"
                class="py-2 text-xs text-center
                       {{ $tab === 'settled' ? 'bg-navy-800 text-white' : 'text-white/60 hover:text-white' }}">
            {{ __('my_bets.tab_settled') }}
        </button>
    </div>

    @if ($votes->isEmpty())
        <div class="mx-3 rounded-xl border border-dashed border-white/10 p-6 text-center text-sm text-white/50">
            {{ $tab === 'active' ? __('my_bets.empty_active') : __('my_bets.empty_settled') }}
        </div>
    @else
        <div class="space-y-2 px-3">
            @foreach ($votes as $vote)
                @php
                    $status = $this->statusFor($vote);
                    $net = $this->netAmountFor($vote);
                    $sideLabel = $vote->side === \App\Models\Battle::SIDE_A ? $vote->battle->side_a_label : $vote->battle->side_b_label;
                @endphp
                <div class="rounded-xl border border-white/5 bg-white/[0.03] p-3">
                    <a href="{{ route('battles.show', $vote->battle) }}"
                       class="block font-semibold text-white/90">{{ $vote->battle->title }}</a>
                    <div class="mt-1 text-[11px] text-white/55">
                        {{ __('my_bets.voted') }}
                        <span class="font-semibold text-white/90">{{ mb_strtoupper($sideLabel) }}</span>
                        · {{ __('my_bets.stake') }} {{ number_format((float) $vote->amount, 0) }}
                        @if ($vote->battle->status === \App\Models\Battle::STATUS_ACTIVE)
                            · {{ $vote->battle->closes_at?->diffForHumans(['parts' => 1, 'short' => true]) }}
                        @elseif ($vote->battle->settled_at)
                            · {{ $vote->battle->settled_at->diffForHumans(['parts' => 1, 'short' => true]) }}
                        @endif
                    </div>
                    <div class="mt-2 flex justify-between items-center">
                        <span @class([
                            'px-2 py-0.5 rounded-full text-[10px] uppercase tracking-wider font-bold',
                            'bg-glow-cyan/15 text-glow-cyan' => $status === 'active',
                            'bg-green-500/15 text-green-300' => $status === 'won',
                            'bg-red-500/15 text-red-300' => $status === 'lost',
                            'bg-white/10 text-white/70' => $status === 'refund',
                        ])>{{ __('my_bets.status_'.$status) }}</span>

                        <span @class([
                            'text-xs',
                            'text-green-300' => $net > 0,
                            'text-red-300' => $net < 0,
                            'text-white/50' => $net === 0.0,
                        ])>
                            @if ($net > 0)+@endif{{ number_format($net, 0) }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="px-3 mt-3">
            {{ $votes->links() }}
        </div>
    @endif
</div>
```

- [ ] **Step 3: Register the route**

Modify `routes/web.php`. Add `use App\Livewire\MyBets;` at the top, then inside the `Route::middleware('auth')->group(...)` block:

```php
Route::get('/my-bets', MyBets::class)->name('my-bets');
```

- [ ] **Step 4: Translations**

Create `lang/en/my_bets.php`:

```php
<?php

return [
    'title' => 'My Bets',
    'tab_active' => 'Active',
    'tab_settled' => 'Settled',
    'voted' => 'Voted',
    'stake' => 'stake',
    'status_active' => 'Active',
    'status_won' => 'Won',
    'status_lost' => 'Lost',
    'status_refund' => 'Refund',
    'empty_active' => 'No active bets. Cast a vote on a battle to see it here.',
    'empty_settled' => 'No settled bets yet.',
];
```

Create `lang/ru/my_bets.php`:

```php
<?php

return [
    'title' => 'Мои ставки',
    'tab_active' => 'Активные',
    'tab_settled' => 'Завершённые',
    'voted' => 'Голос за',
    'stake' => 'ставка',
    'status_active' => 'Активна',
    'status_won' => 'Выиграна',
    'status_lost' => 'Проиграна',
    'status_refund' => 'Возврат',
    'empty_active' => 'Нет активных ставок. Проголосуй в баттле, и он появится здесь.',
    'empty_settled' => 'Завершённых ставок пока нет.',
];
```

- [ ] **Step 5: Remove the `markTestIncomplete` stubs from `BottomNavTest`**

Open `tests/Feature/Http/BottomNavTest.php` and delete the two `$this->markTestIncomplete(...)` lines that were added in Task 18.

- [ ] **Step 6: Run both tests**

```bash
docker compose exec workspace php artisan test --filter='BottomNavTest'
```

Expected: 3 green tests now (home + guest-redirect + authed access).

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/MyBets.php \
        resources/views/livewire/my-bets.blade.php \
        routes/web.php \
        lang/en/my_bets.php lang/ru/my_bets.php \
        tests/Feature/Http/BottomNavTest.php
git commit -m "Add /my-bets route, component, and view"
```

---

### Task 27: `MyBetsTest`

**Files:**

- Create: `tests/Feature/Livewire/MyBetsTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\MyBets;
use App\Models\Battle;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MyBetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_tab_filters_to_votes_on_active_battles(): void
    {
        $user = User::factory()->create();
        $active = Battle::factory()->create();
        $settled = Battle::factory()->settled()->create();

        Vote::factory()->create(['user_id' => $user->id, 'battle_id' => $active->id, 'side' => 'A', 'amount' => 10, 'weight' => 10]);
        Vote::factory()->create(['user_id' => $user->id, 'battle_id' => $settled->id, 'side' => 'A', 'amount' => 20, 'weight' => 20, 'payout' => 100]);

        Livewire::actingAs($user)
            ->test(MyBets::class)
            ->assertViewHas('votes', fn ($v) => $v->count() === 1 && $v->first()->battle_id === $active->id);
    }

    public function test_settled_tab_won_status_and_payout(): void
    {
        $user = User::factory()->create();
        $battle = Battle::factory()->settled(Battle::SIDE_A)->create();
        $vote = Vote::factory()->create([
            'user_id' => $user->id, 'battle_id' => $battle->id,
            'side' => 'A', 'amount' => 50, 'weight' => 50, 'payout' => 250,
        ]);

        $component = Livewire::actingAs($user)->test(MyBets::class)->set('tab', 'settled');
        $this->assertSame('won', $component->instance()->statusFor($vote));
        $this->assertSame(250.0, $component->instance()->netAmountFor($vote));
    }

    public function test_settled_tab_lost_status_and_negative_net(): void
    {
        $user = User::factory()->create();
        $battle = Battle::factory()->settled(Battle::SIDE_A)->create();
        $vote = Vote::factory()->create([
            'user_id' => $user->id, 'battle_id' => $battle->id,
            'side' => 'B', 'amount' => 50, 'weight' => 50, 'payout' => null,
        ]);

        $component = Livewire::actingAs($user)->test(MyBets::class)->set('tab', 'settled');
        $this->assertSame('lost', $component->instance()->statusFor($vote));
        $this->assertSame(-50.0, $component->instance()->netAmountFor($vote));
    }

    public function test_settled_tab_refund_status_on_tie(): void
    {
        $user = User::factory()->create();
        $tied = Battle::factory()->create([
            'status' => Battle::STATUS_SETTLED,
            'winning_side' => null,
            'settled_at' => now(),
        ]);
        $vote = Vote::factory()->create([
            'user_id' => $user->id, 'battle_id' => $tied->id,
            'side' => 'A', 'amount' => 75, 'weight' => 75, 'payout' => 75,
        ]);

        $component = Livewire::actingAs($user)->test(MyBets::class)->set('tab', 'settled');
        $this->assertSame('refund', $component->instance()->statusFor($vote));
        $this->assertSame(75.0, $component->instance()->netAmountFor($vote));
    }

    public function test_multiple_votes_on_same_battle_produce_multiple_rows(): void
    {
        $user = User::factory()->create();
        $battle = Battle::factory()->create();

        Vote::factory()->count(3)->create([
            'user_id' => $user->id, 'battle_id' => $battle->id,
            'side' => 'A', 'amount' => 10, 'weight' => 10,
        ]);

        Livewire::actingAs($user)
            ->test(MyBets::class)
            ->assertViewHas('votes', fn ($v) => $v->count() === 3);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/my-bets')->assertRedirect('/login');
    }
}
```

- [ ] **Step 2: Run**

```bash
docker compose exec workspace php artisan test --filter=MyBetsTest
```

Expected: 6 green tests.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Livewire/MyBetsTest.php
git commit -m "Test MyBets: tabs, status computation, multiple votes, guard"
```

---

## Phase 9 — Final gate

### Task 28: Pint + PHPStan + full test suite

- [ ] **Step 1: Style**

```bash
docker compose exec workspace ./vendor/bin/pint
```

Expected: fixes applied (or no changes). If this changes files, stage them and continue.

- [ ] **Step 2: Static analysis**

```bash
docker compose exec workspace php -d memory_limit=512M ./vendor/bin/phpstan analyse
```

Expected: baseline or clean. If new errors appear, fix them — do not add to the baseline without understanding the root cause.

- [ ] **Step 3: Full test suite**

```bash
docker compose exec workspace php artisan test
```

Expected: all green, including existing tests (vote / settlement / referral / battle-vote-widget).

- [ ] **Step 4: Commit any fixups**

If steps 1-3 produced no changes, skip. Otherwise:

```bash
git add -u
git commit -m "Apply pint/phpstan/test fixups after redesign"
```

---

## Notes for the engineer executing this plan

- **Reuse, don't duplicate:** the existing `BattleVoteWidget` is already redesigned and tested. The Featured card uses it via `<livewire:battle-vote-widget :battle="$featured" :key="...">`. Do not copy its markup elsewhere.
- **Money:** `votes.payout` is the source of truth for per-vote outcomes. Don't re-derive it from `transactions`.
- **Locale:** any new user-facing string goes in both `lang/en/` and `lang/ru/`. Category names are not in lang files — they live on the `categories` row (`name_en`, `name_ru`).
- **DB portability:** tests run on SQLite `:memory:`. `whereRaw('LOWER(col) LIKE ?', [...])` is portable; `ilike` is Postgres-only and must not appear. `selectSub` / `fromSub` / `leftJoinSub` all work on both engines.
- **Pagination:** Livewire's default paginator view is Tailwind. Don't call `resources` publish; just use `{{ $paginator->links() }}`.
- **Middleware:** `/my-bets` uses `auth`; `/leaderboard` does not. Double-check you registered them in the correct route group in `routes/web.php`.
- **Visual QA:** when the plan lands, manually verify in a real browser at `http://versus.local/`:
  - Mobile viewport: Featured card renders, Hot list has 3 items, chips scroll horizontally, Finished chip switches to settled mode, bottom nav is fixed at the bottom, search overlay opens from the top-bar icon.
  - Desktop viewport: sidebar widgets show on the right, top-bar search icon is present, no bottom nav, Top Players shows the winnings-based ranking.

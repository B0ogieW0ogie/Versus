# Profile Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the Breeze `/profile` edit form with a mobile-first profile page (banner, avatar, stats, tabbed content: Activity / Versus Creation / Comments / Referrals), move editing to `/profile/settings`, and absorb `/my-bets` and `/referrals` into tabs.

**Architecture:** Single Livewire component `App\Livewire\ProfilePage` at `GET /profile` with 4 tab partials (URL-persisted via `#[Url]`). Data plumbed through: user's votes (Activity), user's comments (Comments), referral link + referred users + earned total (Referrals). Hardcoded display values: Subscribers=352, Following=128, RP=2,450, title="Architect of Reality". Settings form keeps the existing Breeze partials but moves to `/profile/settings/*` and adds `username`, `bio`, `avatar_path`, `banner_path` columns on `users`.

**Tech Stack:** Laravel 13 · Livewire 4 · Pest 4 · Tailwind · PostgreSQL (dev) / SQLite :memory: (tests).

**Reference spec:** [2026-04-23-profile-page-design.md](../specs/2026-04-23-profile-page-design.md)

---

## File Structure

### New files
- `database/migrations/<ts>_add_profile_fields_to_users_table.php` — adds `username`, `bio`, `avatar_path`, `banner_path`.
- `app/Livewire/ProfilePage.php` — the page component (tab state, activity/comments/referrals data).
- `resources/views/livewire/profile-page.blade.php` — root view (header, banner, avatar, stats, bio, tab bar, `@switch($tab)` to partials).
- `resources/views/livewire/profile/tabs/activity.blade.php` — user's votes list.
- `resources/views/livewire/profile/tabs/creation.blade.php` — "Coming soon" placeholder.
- `resources/views/livewire/profile/tabs/comments.blade.php` — user's comments list.
- `resources/views/livewire/profile/tabs/referrals.blade.php` — referral link, referred users, earned total.
- `lang/en/profile.php`, `lang/ru/profile.php` — i18n keys.
- `tests/Feature/Profile/ProfilePageTest.php` — page rendering + tab behaviour.
- `tests/Feature/Profile/ProfileSettingsUpdateTest.php` — extended update tests for new fields.
- `tests/Feature/Navigation/ProfileRedirectsTest.php` — `/my-bets`, `/referrals` redirects.

### Modified files
- `app/Models/User.php` — fillable + `avatarUrl()`, `bannerUrl()` accessors.
- `app/Http/Controllers/ProfileController.php` — handle username/bio/avatar/banner; redirect to `profile.settings`.
- `app/Http/Requests/ProfileUpdateRequest.php` — new validation rules.
- `resources/views/profile/edit.blade.php` — dark layout + new fields.
- `resources/views/profile/partials/update-profile-information-form.blade.php` — username/bio/avatar/banner inputs.
- `routes/web.php` — move profile routes under `/profile/settings/*` (rename to `profile.settings.*`), mount `ProfilePage` at `/profile`, replace `/referrals` and `/my-bets` with redirects.
- `resources/views/layouts/navigation.blade.php` — drop desktop "Referrals" link.
- `lang/en/nav.php`, `lang/ru/nav.php` — remove unused `referrals` key.
- `tests/Feature/ProfileTest.php` — update to use `/profile/settings/*` URLs.

### Deleted files
- `app/Livewire/MyBets.php`
- `app/Livewire/ReferralPanel.php`
- `resources/views/livewire/my-bets.blade.php`
- `resources/views/livewire/referral-panel.blade.php`
- `tests/Feature/Livewire/MyBetsTest.php`
- `lang/en/my_bets.php`, `lang/ru/my_bets.php` (if present — check during Task 15)

---

## Conventions for all tasks

- All dev commands go through the workspace container via the Makefile. **Never invoke `php`, `composer`, `npm` or `artisan` directly on the host.**
- To run a single Pest test: `make ws` to get a shell, then `vendor/bin/pest --filter=<name>` or `php artisan test --filter=<name>`.
- To run the whole suite: `make test`.
- Style: `make pint`. Static analysis: `make stan` (or `npm run stan` inside workspace for the larger memory limit — PHPStan default 128M crashes on `CastVoteAction`).
- Commit after each task. Use present-tense imperative commit messages, keep them short (one line).

---

## Task 1: DB migration — add profile fields to users

**Files:**
- Create: `database/migrations/<timestamp>_add_profile_fields_to_users_table.php`
- Modify: `app/Models/User.php`

- [ ] **Step 1: Generate the migration**

Run:
```
make art CMD="make:migration add_profile_fields_to_users_table"
```
Expected: a new file appears under `database/migrations/` with today's date prefix. Note the path for the next step.

- [ ] **Step 2: Write the migration body**

Replace the generated file's contents with:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 32)->nullable()->unique()->after('email');
            $table->text('bio')->nullable()->after('username');
            $table->string('avatar_path')->nullable()->after('bio');
            $table->string('banner_path')->nullable()->after('avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'bio', 'avatar_path', 'banner_path']);
        });
    }
};
```

- [ ] **Step 3: Write a failing migration test**

Create `tests/Feature/Profile/UserProfileFieldsMigrationTest.php`:
```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('users table has new profile columns', function () {
    $user = User::factory()->create([
        'username' => 'alice',
        'bio' => 'hello',
        'avatar_path' => 'avatars/a.png',
        'banner_path' => 'banners/b.png',
    ]);

    expect($user->fresh())
        ->username->toBe('alice')
        ->bio->toBe('hello')
        ->avatar_path->toBe('avatars/a.png')
        ->banner_path->toBe('banners/b.png');
});

test('username must be unique', function () {
    User::factory()->create(['username' => 'alice']);

    expect(fn () => User::factory()->create(['username' => 'alice']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 4: Run it to verify it fails**

Run: `make ws` then `vendor/bin/pest tests/Feature/Profile/UserProfileFieldsMigrationTest.php`
Expected: **FAIL** — `username`, `bio` etc. not fillable on `User`, and columns present but not mass-assignable.

- [ ] **Step 5: Add the new fields to User mass-assignment**

In `app/Models/User.php` replace the `#[Fillable([...])]` attribute:
```php
#[Fillable(['name', 'email', 'password', 'referred_by_id', 'username', 'bio', 'avatar_path', 'banner_path'])]
```

- [ ] **Step 6: Re-run the test**

Run: `vendor/bin/pest tests/Feature/Profile/UserProfileFieldsMigrationTest.php`
Expected: **PASS** (2 tests).

- [ ] **Step 7: Run the full suite**

Run: `make test`
Expected: no new failures (existing tests unaffected — new columns all nullable).

- [ ] **Step 8: Commit**

```
git add database/migrations app/Models/User.php tests/Feature/Profile
git commit -m "Add profile fields (username, bio, avatar_path, banner_path) to users"
```

---

## Task 2: User model accessors for avatar / banner URLs

**Files:**
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Profile/UserProfileAccessorsTest.php` (new)

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Profile/UserProfileAccessorsTest.php`:
```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('avatarUrl returns null when avatar_path is null', function () {
    $user = User::factory()->create(['avatar_path' => null]);

    expect($user->avatarUrl())->toBeNull();
});

test('avatarUrl returns public storage url when path is set', function () {
    Storage::fake('public');
    $user = User::factory()->create(['avatar_path' => 'avatars/a.png']);

    expect($user->avatarUrl())->toBe(Storage::disk('public')->url('avatars/a.png'));
});

test('bannerUrl mirrors avatarUrl behaviour', function () {
    Storage::fake('public');
    $user = User::factory()->create(['banner_path' => 'banners/b.png']);

    expect($user->bannerUrl())->toBe(Storage::disk('public')->url('banners/b.png'));
});
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/pest tests/Feature/Profile/UserProfileAccessorsTest.php`
Expected: **FAIL** — `avatarUrl()` / `bannerUrl()` not defined.

- [ ] **Step 3: Implement accessors**

In `app/Models/User.php`, add near the bottom of the class (before the final `}`):
```php
public function avatarUrl(): ?string
{
    return $this->avatar_path
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->avatar_path)
        : null;
}

public function bannerUrl(): ?string
{
    return $this->banner_path
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->banner_path)
        : null;
}
```

- [ ] **Step 4: Re-run the test**

Run: `vendor/bin/pest tests/Feature/Profile/UserProfileAccessorsTest.php`
Expected: **PASS** (3 tests).

- [ ] **Step 5: Commit**

```
git add app/Models/User.php tests/Feature/Profile/UserProfileAccessorsTest.php
git commit -m "Add avatarUrl / bannerUrl accessors on User"
```

---

## Task 3: i18n keys

**Files:**
- Create: `lang/en/profile.php`, `lang/ru/profile.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Profile/ProfileTranslationsTest.php`:
```php
<?php

test('profile translations exist in en', function () {
    app()->setLocale('en');

    expect(__('profile.tab_activity'))->toBe('Activity');
    expect(__('profile.subscribers'))->toBe('Subscribers');
    expect(__('profile.coming_soon'))->toBe('Coming soon');
});

test('profile translations exist in ru', function () {
    app()->setLocale('ru');

    expect(__('profile.tab_activity'))->toBe('Активность');
    expect(__('profile.subscribers'))->toBe('Подписчики');
    expect(__('profile.coming_soon'))->toBe('Скоро');
});
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/pest tests/Feature/Profile/ProfileTranslationsTest.php`
Expected: **FAIL** — `__('profile.tab_activity')` returns the raw key.

- [ ] **Step 3: Create the English file**

Create `lang/en/profile.php`:
```php
<?php

return [
    'title' => 'Profile',
    'subscribers' => 'Subscribers',
    'following' => 'Following',
    'edit' => 'EDIT',
    'rp_suffix' => 'RP',
    'username_fallback_prefix' => 'user',
    'tab_activity' => 'Activity',
    'tab_creation' => 'Versus Creation',
    'tab_comments' => 'Comments',
    'tab_referrals' => 'Referrals',
    'coming_soon' => 'Coming soon',
    'activity_empty' => 'No bets yet.',
    'comments_empty' => 'No comments yet.',
    'activity_you_voted' => 'You voted:',
    'activity_amount' => 'Amount:',
    'activity_vrs' => 'VRS',
    'activity_badge_win' => 'WIN',
    'activity_badge_lose' => 'LOSE',
    'activity_badge_active' => 'ACTIVE',
    'activity_badge_refund' => 'REFUND',
    'comments_on' => 'on:',
    'referrals_link_heading' => 'Your referral link',
    'referrals_copy' => 'Copy',
    'referrals_copied' => 'Copied!',
    'referrals_list_heading' => 'Your referrals',
    'referrals_earned' => 'Earned',
    'referrals_empty' => 'Nobody has signed up through your link yet.',
    'referrals_joined' => 'joined :when',
    'settings_title' => 'Profile settings',
    'username_label' => 'Username',
    'username_help' => '3–32 characters, letters, digits and underscores.',
    'bio_label' => 'Bio',
    'bio_help' => 'Up to 500 characters.',
    'avatar_label' => 'Avatar',
    'banner_label' => 'Banner',
];
```

- [ ] **Step 4: Create the Russian file**

Create `lang/ru/profile.php`:
```php
<?php

return [
    'title' => 'Профиль',
    'subscribers' => 'Подписчики',
    'following' => 'Подписки',
    'edit' => 'РЕДАКТИРОВАТЬ',
    'rp_suffix' => 'RP',
    'username_fallback_prefix' => 'user',
    'tab_activity' => 'Активность',
    'tab_creation' => 'Versus Creation',
    'tab_comments' => 'Комментарии',
    'tab_referrals' => 'Рефералы',
    'coming_soon' => 'Скоро',
    'activity_empty' => 'Ставок пока нет.',
    'comments_empty' => 'Комментариев пока нет.',
    'activity_you_voted' => 'Вы голосовали:',
    'activity_amount' => 'Ставка:',
    'activity_vrs' => 'VRS',
    'activity_badge_win' => 'WIN',
    'activity_badge_lose' => 'LOSE',
    'activity_badge_active' => 'ACTIVE',
    'activity_badge_refund' => 'REFUND',
    'comments_on' => 'под:',
    'referrals_link_heading' => 'Ваша реферальная ссылка',
    'referrals_copy' => 'Копировать',
    'referrals_copied' => 'Скопировано!',
    'referrals_list_heading' => 'Ваши рефералы',
    'referrals_earned' => 'Заработано',
    'referrals_empty' => 'По вашей ссылке пока никто не зарегистрировался.',
    'referrals_joined' => 'присоединился :when',
    'settings_title' => 'Настройки профиля',
    'username_label' => 'Имя пользователя',
    'username_help' => '3–32 символа, латиница, цифры и подчёркивание.',
    'bio_label' => 'О себе',
    'bio_help' => 'До 500 символов.',
    'avatar_label' => 'Аватар',
    'banner_label' => 'Баннер',
];
```

- [ ] **Step 5: Re-run the test**

Run: `vendor/bin/pest tests/Feature/Profile/ProfileTranslationsTest.php`
Expected: **PASS** (2 tests).

- [ ] **Step 6: Commit**

```
git add lang/en/profile.php lang/ru/profile.php tests/Feature/Profile/ProfileTranslationsTest.php
git commit -m "Add profile i18n keys (en + ru)"
```

---

## Task 4: ProfilePage Livewire skeleton

**Files:**
- Create: `app/Livewire/ProfilePage.php`
- Create: `resources/views/livewire/profile-page.blade.php`
- Test: `tests/Feature/Profile/ProfilePageTest.php` (new)

This task only mounts the component at a new, temporary route. Route reassignment happens in Task 5.

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Profile/ProfilePageTest.php`:
```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('renders for authenticated user', function () {
    $user = User::factory()->create(['name' => 'Alice']);

    Livewire::actingAs($user)
        ->test(\App\Livewire\ProfilePage::class)
        ->assertOk()
        ->assertSee('Alice');
});
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/pest tests/Feature/Profile/ProfilePageTest.php`
Expected: **FAIL** — `App\Livewire\ProfilePage` class not found.

- [ ] **Step 3: Create the component**

Create `app/Livewire/ProfilePage.php`:
```php
<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

class ProfilePage extends Component
{
    #[Layout('layouts.app')]
    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        return view('livewire.profile-page', [
            'user' => $user,
        ]);
    }
}
```

- [ ] **Step 4: Create the minimal view**

Create `resources/views/livewire/profile-page.blade.php`:
```blade
<div class="max-w-2xl mx-auto pt-4 pb-20">
    <h1 class="text-xl font-semibold text-white px-4">{{ $user->name }}</h1>
</div>
```

- [ ] **Step 5: Re-run the test**

Run: `vendor/bin/pest tests/Feature/Profile/ProfilePageTest.php`
Expected: **PASS**.

- [ ] **Step 6: Commit**

```
git add app/Livewire/ProfilePage.php resources/views/livewire/profile-page.blade.php tests/Feature/Profile/ProfilePageTest.php
git commit -m "Scaffold ProfilePage Livewire component"
```

---

## Task 5: Move settings routes to /profile/settings; repoint /profile → ProfilePage

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/ProfileController.php`
- Modify: `tests/Feature/ProfileTest.php`

After this task, `GET /profile` renders ProfilePage; all editing (PATCH, DELETE) happens at `/profile/settings`. Route name `profile.edit` is reassigned to the new page; settings routes become `profile.settings` / `profile.settings.update` / `profile.settings.destroy`.

- [ ] **Step 1: Update route definitions**

Open `routes/web.php` and find the existing `Route::middleware('auth')->group(...)` block:
```php
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    // ...
});
```

Replace those three profile lines with:
```php
    Route::get('/profile', \App\Livewire\ProfilePage::class)->name('profile.edit');
    Route::get('/profile/settings', [ProfileController::class, 'edit'])->name('profile.settings');
    Route::patch('/profile/settings', [ProfileController::class, 'update'])->name('profile.settings.update');
    Route::delete('/profile/settings', [ProfileController::class, 'destroy'])->name('profile.settings.destroy');
```

Leave the other routes in the group (e.g. `/referrals`, `/my-bets`) untouched for now — we replace them in Task 14.

Also remove the obsolete `use App\Http\Controllers\ProfileController;` import only if it's no longer referenced; it IS still referenced, so keep it.

- [ ] **Step 2: Update the controller redirect**

In `app/Http/Controllers/ProfileController.php`, change the line in `update()`:

```php
return Redirect::route('profile.edit')->with('status', 'profile-updated');
```

to:

```php
return Redirect::route('profile.settings')->with('status', 'profile-updated');
```

- [ ] **Step 3: Update existing Breeze profile tests**

Open `tests/Feature/ProfileTest.php`. Replace the whole class body with:
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_settings_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile/settings')
            ->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile/settings', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile/settings');

        $user->refresh();
        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_email_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile/settings', [
                'name' => 'Test User',
                'email' => $user->email,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile/settings');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete('/profile/settings', ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_wrong_password_prevents_account_deletion(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/profile/settings')
            ->delete('/profile/settings', ['password' => 'wrong-password'])
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile/settings');

        $this->assertNotNull($user->fresh());
    }
}
```

- [ ] **Step 4: Add a ProfilePage test case for `/profile` routing**

Append to `tests/Feature/Profile/ProfilePageTest.php`:
```php
test('guest is redirected to login', function () {
    $this->get('/profile')->assertRedirect(route('login'));
});

test('authenticated user sees the profile page at /profile', function () {
    $user = \App\Models\User::factory()->create(['name' => 'Alice']);

    $this->actingAs($user)
        ->get('/profile')
        ->assertOk()
        ->assertSee('Alice');
});
```

- [ ] **Step 5: Run the full test suite**

Run: `make test`
Expected: all tests pass. The `ProfileTest` cases now exercise `/profile/settings`, and `ProfilePageTest` exercises `/profile`.

- [ ] **Step 6: Commit**

```
git add routes/web.php app/Http/Controllers/ProfileController.php tests/Feature/ProfileTest.php tests/Feature/Profile/ProfilePageTest.php
git commit -m "Move profile settings under /profile/settings, mount ProfilePage at /profile"
```

---

## Task 6: Visual shell — header, banner, avatar, stats, RP, name, handle, title, bio

**Files:**
- Modify: `resources/views/livewire/profile-page.blade.php`
- Test: `tests/Feature/Profile/ProfilePageTest.php`

Hardcoded display values as per spec: subscribers `352`, following `128`, RP `2,450`, title `Architect of Reality`.

- [ ] **Step 1: Add failing tests for the shell**

Append to `tests/Feature/Profile/ProfilePageTest.php`:
```php
test('shows user name, handle, bio and hardcoded stats', function () {
    $user = \App\Models\User::factory()->create([
        'name' => 'Vlad Basargin',
        'username' => 'vladbasargin',
        'bio' => 'Люблю спорить о футболе',
    ]);

    $this->actingAs($user)
        ->get('/profile')
        ->assertSee('Vlad Basargin')
        ->assertSee('@vladbasargin')
        ->assertSee('Architect of Reality')
        ->assertSee('Люблю спорить о футболе', escape: false)
        ->assertSee('352')
        ->assertSee('128')
        ->assertSee('2,450');
});

test('falls back to @user{id} when username is null', function () {
    $user = \App\Models\User::factory()->create(['username' => null]);

    $this->actingAs($user)
        ->get('/profile')
        ->assertSee('@user' . $user->id);
});

test('edit button links to profile settings route', function () {
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)
        ->get('/profile')
        ->assertSee(route('profile.settings'), escape: false);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/pest tests/Feature/Profile/ProfilePageTest.php`
Expected: **FAIL** — none of the hardcoded numbers or the title string are in the bare view yet.

- [ ] **Step 3: Replace the view with the full shell**

Replace the entire contents of `resources/views/livewire/profile-page.blade.php`:
```blade
@php
    $handle = $user->username ? '@' . $user->username : '@' . __('profile.username_fallback_prefix') . $user->id;
    $title = 'Architect of Reality';
@endphp

<div class="pb-20">
    {{-- Header --}}
    <header class="sticky top-0 z-10 bg-navy-900/95 backdrop-blur px-4 h-12 flex items-center justify-between border-b border-white/5">
        <h1 class="text-base font-semibold text-white">{{ __('profile.title') }}</h1>
        <div class="flex items-center gap-1 text-white/40">
            <button type="button" disabled aria-disabled="true"
                    title="{{ __('profile.coming_soon') }}"
                    class="p-2 cursor-not-allowed">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                     stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                </svg>
            </button>
            <button type="button" disabled aria-disabled="true"
                    title="{{ __('profile.coming_soon') }}"
                    class="p-2 cursor-not-allowed">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                     stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                    <circle cx="5" cy="12" r="1.5" fill="currentColor"/>
                    <circle cx="12" cy="12" r="1.5" fill="currentColor"/>
                    <circle cx="19" cy="12" r="1.5" fill="currentColor"/>
                </svg>
            </button>
        </div>
    </header>

    <div class="max-w-2xl mx-auto">
        {{-- Banner --}}
        <div class="aspect-[16/7] bg-white/5 flex items-center justify-center overflow-hidden">
            @if ($user->bannerUrl())
                <img src="{{ $user->bannerUrl() }}" alt="" class="w-full h-full object-cover">
            @else
                <x-icon.image-placeholder class="h-12 w-12 text-white/20" />
            @endif
        </div>

        {{-- Header row: avatar + stats + edit --}}
        <div class="px-4 -mt-12 flex items-end gap-4">
            <div class="h-24 w-24 rounded-full bg-navy-700 ring-4 ring-navy-900 overflow-hidden flex items-center justify-center">
                @if ($user->avatarUrl())
                    <img src="{{ $user->avatarUrl() }}" alt="" class="w-full h-full object-cover">
                @else
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                         class="h-12 w-12 text-white/30">
                        <path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-4.418 0-8 2.686-8 6v2h16v-2c0-3.314-3.582-6-8-6z"/>
                    </svg>
                @endif
            </div>

            <div class="flex-1 flex items-end justify-between pb-1">
                <div class="flex gap-6">
                    <div class="text-center">
                        <div class="text-lg font-bold text-white">352</div>
                        <div class="text-[11px] text-white/55">{{ __('profile.subscribers') }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-lg font-bold text-white">128</div>
                        <div class="text-[11px] text-white/55">{{ __('profile.following') }}</div>
                    </div>
                </div>
                <a href="{{ route('profile.settings') }}"
                   class="text-xs font-semibold text-white border border-white/20 rounded-lg px-4 py-1.5 hover:bg-white/5 transition">
                    {{ __('profile.edit') }}
                </a>
            </div>
        </div>

        {{-- RP --}}
        <div class="px-4 mt-2 flex items-center gap-1.5 text-sm text-white/70">
            <x-icon.trophy class="h-4 w-4" />
            <span class="font-semibold text-white">2,450</span>
            <span>{{ __('profile.rp_suffix') }}</span>
        </div>

        {{-- Name + handle + title --}}
        <div class="px-4 mt-3">
            <h2 class="text-2xl font-bold text-white">{{ $user->name }}</h2>
            <div class="mt-0.5 text-sm text-white/60 flex flex-wrap gap-x-2 items-center">
                <span>{{ $handle }}</span>
                <span class="text-white/40">·</span>
                <span class="text-vote-purple-to font-semibold">{{ $title }}</span>
            </div>
        </div>

        {{-- Bio --}}
        @if ($user->bio)
            <p class="px-4 mt-2 text-sm text-white/80 whitespace-pre-line">{{ $user->bio }}</p>
        @endif
    </div>
</div>
```

- [ ] **Step 4: Re-run the tests**

Run: `vendor/bin/pest tests/Feature/Profile/ProfilePageTest.php`
Expected: **PASS** (all cases green).

- [ ] **Step 5: Commit**

```
git add resources/views/livewire/profile-page.blade.php tests/Feature/Profile/ProfilePageTest.php
git commit -m "ProfilePage visual shell: header, banner, avatar, stats, name, bio"
```

---

## Task 7: Tab bar with URL persistence

**Files:**
- Modify: `app/Livewire/ProfilePage.php`
- Modify: `resources/views/livewire/profile-page.blade.php`
- Create: `resources/views/livewire/profile/tabs/activity.blade.php`, `creation.blade.php`, `comments.blade.php`, `referrals.blade.php`
- Test: `tests/Feature/Profile/ProfilePageTest.php`

- [ ] **Step 1: Add failing tests for tab behaviour**

Append to `tests/Feature/Profile/ProfilePageTest.php`:
```php
test('activity tab is active by default', function () {
    $user = \App\Models\User::factory()->create();

    \Livewire\Livewire::actingAs($user)
        ->test(\App\Livewire\ProfilePage::class)
        ->assertSet('tab', 'activity');
});

test('tab is switchable via url', function () {
    $user = \App\Models\User::factory()->create();

    \Livewire\Livewire::actingAs($user)
        ->withQueryParams(['tab' => 'comments'])
        ->test(\App\Livewire\ProfilePage::class)
        ->assertSet('tab', 'comments');
});

test('invalid tab param falls back to activity', function () {
    $user = \App\Models\User::factory()->create();

    \Livewire\Livewire::actingAs($user)
        ->withQueryParams(['tab' => 'garbage'])
        ->test(\App\Livewire\ProfilePage::class)
        ->assertSet('tab', 'activity');
});

test('creation tab shows coming soon', function () {
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)
        ->get('/profile?tab=creation')
        ->assertSee(__('profile.coming_soon'));
});
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/pest tests/Feature/Profile/ProfilePageTest.php`
Expected: **FAIL** — no `$tab` property yet.

- [ ] **Step 3: Add tab state to the component**

Replace `app/Livewire/ProfilePage.php` with:
```php
<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

class ProfilePage extends Component
{
    private const TABS = ['activity', 'creation', 'comments', 'referrals'];

    #[Url]
    public string $tab = 'activity';

    public function mount(): void
    {
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'activity';
        }
    }

    public function selectTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'activity';
    }

    #[Layout('layouts.app')]
    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        return view('livewire.profile-page', [
            'user' => $user,
        ]);
    }
}
```

- [ ] **Step 4: Create the four tab partials as placeholders**

Create `resources/views/livewire/profile/tabs/activity.blade.php`:
```blade
<div class="px-4 mt-4 text-sm text-white/60">{{ __('profile.tab_activity') }}</div>
```

Create `resources/views/livewire/profile/tabs/creation.blade.php`:
```blade
<div class="px-4 mt-10 text-center text-sm text-white/50">{{ __('profile.coming_soon') }}</div>
```

Create `resources/views/livewire/profile/tabs/comments.blade.php`:
```blade
<div class="px-4 mt-4 text-sm text-white/60">{{ __('profile.tab_comments') }}</div>
```

Create `resources/views/livewire/profile/tabs/referrals.blade.php`:
```blade
<div class="px-4 mt-4 text-sm text-white/60">{{ __('profile.tab_referrals') }}</div>
```

- [ ] **Step 5: Add the tab bar + content switch to the main view**

In `resources/views/livewire/profile-page.blade.php`, add this block just before the final closing `</div>` of the `max-w-2xl mx-auto` container (i.e. right after the bio `<p>` and its surrounding `@if`):
```blade
        {{-- Tab bar --}}
        <div class="mt-4 px-4 flex items-end gap-4 border-b border-white/5">
            @foreach (['activity', 'creation', 'comments', 'referrals'] as $key)
                <button type="button"
                        wire:click="selectTab('{{ $key }}')"
                        class="pb-2 text-xs font-semibold tracking-wide transition
                               {{ $tab === $key ? 'text-white border-b-2 border-vote-purple-to -mb-px' : 'text-white/50 hover:text-white/80' }}">
                    {{ __('profile.tab_' . $key) }}
                </button>
            @endforeach
            <div class="flex-1"></div>
            <button type="button" disabled aria-disabled="true"
                    title="{{ __('profile.coming_soon') }}"
                    class="mb-1 p-1.5 rounded-lg border border-white/10 text-white/35 cursor-not-allowed">
                <x-icon.trophy class="h-4 w-4" />
            </button>
        </div>

        {{-- Tab content --}}
        @switch($tab)
            @case('creation')
                @include('livewire.profile.tabs.creation')
                @break
            @case('comments')
                @include('livewire.profile.tabs.comments')
                @break
            @case('referrals')
                @include('livewire.profile.tabs.referrals')
                @break
            @default
                @include('livewire.profile.tabs.activity')
        @endswitch
```

- [ ] **Step 6: Re-run the tests**

Run: `vendor/bin/pest tests/Feature/Profile/ProfilePageTest.php`
Expected: **PASS**.

- [ ] **Step 7: Commit**

```
git add app/Livewire/ProfilePage.php resources/views/livewire
git commit -m "ProfilePage: tab bar with URL-persisted state"
```

---

## Task 8: Activity tab — port MyBets logic

**Files:**
- Modify: `app/Livewire/ProfilePage.php`
- Modify: `resources/views/livewire/profile/tabs/activity.blade.php`
- Test: `tests/Feature/Profile/ProfilePageTest.php`

Port the `statusFor` / `netAmountFor` helpers from `MyBets`. Unlike MyBets, we do **not** split active vs settled — one chronological list.

- [ ] **Step 1: Write failing tests**

Append to `tests/Feature/Profile/ProfilePageTest.php`:
```php
test('activity tab lists user votes with win/lose/active badges', function () {
    $user = \App\Models\User::factory()->create();
    $other = \App\Models\User::factory()->create();

    // One active battle with a vote from the user
    $activeBattle = \App\Models\Battle::factory()->create([
        'title' => 'Alpha vs Beta',
        'status' => \App\Models\Battle::STATUS_ACTIVE,
        'side_a_label' => 'Alpha',
        'side_b_label' => 'Beta',
    ]);
    \App\Models\Vote::factory()->create([
        'user_id' => $user->id,
        'battle_id' => $activeBattle->id,
        'side' => \App\Models\Battle::SIDE_A,
        'amount' => 500,
        'payout' => null,
    ]);

    // One settled battle where the user won
    $wonBattle = \App\Models\Battle::factory()->create([
        'title' => 'Gamma vs Delta',
        'status' => \App\Models\Battle::STATUS_SETTLED,
        'winning_side' => \App\Models\Battle::SIDE_A,
        'settled_at' => now(),
        'side_a_label' => 'Gamma',
        'side_b_label' => 'Delta',
    ]);
    \App\Models\Vote::factory()->create([
        'user_id' => $user->id,
        'battle_id' => $wonBattle->id,
        'side' => \App\Models\Battle::SIDE_A,
        'amount' => 300,
        'payout' => 800,
    ]);

    // Noise: a vote from another user must not appear
    $noiseBattle = \App\Models\Battle::factory()->create(['title' => 'NOISE vs NOISE']);
    \App\Models\Vote::factory()->create([
        'user_id' => $other->id,
        'battle_id' => $noiseBattle->id,
    ]);

    $response = $this->actingAs($user)->get('/profile?tab=activity');

    $response->assertSee('Alpha vs Beta')
        ->assertSee('Gamma vs Delta')
        ->assertSee(__('profile.activity_badge_active'))
        ->assertSee(__('profile.activity_badge_win'))
        ->assertDontSee('NOISE vs NOISE');
});

test('activity tab shows empty state when user has no votes', function () {
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)
        ->get('/profile?tab=activity')
        ->assertSee(__('profile.activity_empty'));
});
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/pest tests/Feature/Profile/ProfilePageTest.php`
Expected: **FAIL** — placeholder partial has no list.

- [ ] **Step 3: Add data loading and helpers to the component**

Replace `app/Livewire/ProfilePage.php` with:
```php
<?php

namespace App\Livewire;

use App\Models\Battle;
use App\Models\Comment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ProfilePage extends Component
{
    use WithPagination;

    private const TABS = ['activity', 'creation', 'comments', 'referrals'];

    #[Url]
    public string $tab = 'activity';

    public function mount(): void
    {
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'activity';
        }
    }

    public function selectTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'activity';
        $this->resetPage();
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

        return $vote->side === $battle->winning_side ? 'win' : 'lose';
    }

    public function netAmountFor(Vote $vote): float
    {
        return match ($this->statusFor($vote)) {
            'win', 'refund' => (float) ($vote->payout ?? 0),
            'lose' => -(float) $vote->amount,
            default => 0.0,
        };
    }

    #[Layout('layouts.app')]
    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        return view('livewire.profile-page', [
            'user' => $user,
            'votes' => $this->loadVotes($user),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Vote>
     */
    private function loadVotes(User $user): LengthAwarePaginator
    {
        return Vote::query()
            ->where('user_id', $user->id)
            ->with(['battle:id,title,slug,status,side_a_label,side_b_label,winning_side,total_pool,closes_at,settled_at'])
            ->latest()
            ->paginate(20);
    }
}
```

- [ ] **Step 4: Replace the Activity partial**

Replace `resources/views/livewire/profile/tabs/activity.blade.php` with:
```blade
<div class="mt-3">
    @if ($votes->isEmpty())
        <div class="mx-4 rounded-xl border border-dashed border-white/10 p-6 text-center text-sm text-white/50">
            {{ __('profile.activity_empty') }}
        </div>
    @else
        <ul class="space-y-2 px-4">
            @foreach ($votes as $vote)
                @php
                    $status = $this->statusFor($vote);
                    $net = $this->netAmountFor($vote);
                    $sideLabel = $vote->side === \App\Models\Battle::SIDE_A
                        ? $vote->battle->side_a_label
                        : $vote->battle->side_b_label;
                @endphp
                <li class="rounded-xl border border-white/5 bg-white/[0.03] p-3 flex gap-3">
                    <div class="h-12 w-20 bg-white/5 rounded-md flex-shrink-0 flex items-center justify-center text-[10px] text-white/40">VS</div>
                    <div class="flex-1 min-w-0">
                        <a href="{{ route('battles.show', $vote->battle) }}"
                           class="block font-semibold text-white/90 truncate">{{ $vote->battle->title }}</a>
                        <div class="text-[11px] text-white/55 mt-0.5">
                            {{ __('profile.activity_you_voted') }}
                            <span class="text-white/80 font-medium">{{ $sideLabel }}</span>
                        </div>
                        <div class="text-[11px] text-white/55">
                            {{ __('profile.activity_amount') }}
                            {{ number_format((float) $vote->amount, 0) }} {{ __('profile.activity_vrs') }}
                        </div>
                    </div>
                    <div class="text-right flex flex-col items-end justify-between">
                        <span @class([
                            'px-2 py-0.5 rounded-full text-[10px] uppercase tracking-wider font-bold',
                            'bg-glow-cyan/15 text-glow-cyan' => $status === 'active',
                            'bg-green-500/15 text-green-300' => $status === 'win',
                            'bg-red-500/15 text-red-300' => $status === 'lose',
                            'bg-white/10 text-white/70' => $status === 'refund',
                        ])>{{ __('profile.activity_badge_' . $status) }}</span>

                        <span @class([
                            'text-xs',
                            'text-green-300' => $net > 0,
                            'text-red-300' => $net < 0,
                            'text-white/40' => $net === 0.0,
                        ])>
                            @if ($net > 0)+@endif{{ number_format($net, 0) }} {{ __('profile.activity_vrs') }}
                        </span>

                        <span class="text-[10px] text-white/40">
                            @if ($vote->battle->status === \App\Models\Battle::STATUS_ACTIVE)
                                {{ $vote->battle->closes_at?->diffForHumans(['parts' => 1, 'short' => true]) }}
                            @elseif ($vote->battle->settled_at)
                                {{ $vote->battle->settled_at->diffForHumans(['parts' => 1, 'short' => true]) }}
                            @endif
                        </span>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="px-4 mt-3">
            {{ $votes->links() }}
        </div>
    @endif
</div>
```

- [ ] **Step 5: Re-run tests**

Run: `vendor/bin/pest tests/Feature/Profile/ProfilePageTest.php`
Expected: **PASS**.

- [ ] **Step 6: Commit**

```
git add app/Livewire/ProfilePage.php resources/views/livewire/profile/tabs/activity.blade.php tests/Feature/Profile/ProfilePageTest.php
git commit -m "ProfilePage: Activity tab with user votes list"
```

---

## Task 9: Comments tab

**Files:**
- Modify: `app/Livewire/ProfilePage.php`
- Modify: `resources/views/livewire/profile/tabs/comments.blade.php`
- Test: `tests/Feature/Profile/ProfilePageTest.php`

- [ ] **Step 1: Write failing tests**

Append to `tests/Feature/Profile/ProfilePageTest.php`:
```php
test('comments tab lists user comments with battle link', function () {
    $user = \App\Models\User::factory()->create();
    $other = \App\Models\User::factory()->create();

    $battle = \App\Models\Battle::factory()->create(['title' => 'Pepsi vs Coke']);

    \App\Models\Comment::factory()->create([
        'user_id' => $user->id,
        'battle_id' => $battle->id,
        'body' => 'My hot take',
    ]);
    \App\Models\Comment::factory()->create([
        'user_id' => $other->id,
        'battle_id' => $battle->id,
        'body' => 'Someone else',
    ]);

    $this->actingAs($user)
        ->get('/profile?tab=comments')
        ->assertSee('My hot take')
        ->assertSee('Pepsi vs Coke')
        ->assertDontSee('Someone else');
});

test('comments tab shows empty state', function () {
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)
        ->get('/profile?tab=comments')
        ->assertSee(__('profile.comments_empty'));
});
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/pest tests/Feature/Profile/ProfilePageTest.php`
Expected: **FAIL** — Comments partial is still the placeholder.

- [ ] **Step 3: Add comments loading to the component**

In `app/Livewire/ProfilePage.php`, add a private loader method (next to `loadVotes`):
```php
/**
 * @return LengthAwarePaginator<int, Comment>
 */
private function loadComments(User $user): LengthAwarePaginator
{
    return Comment::query()
        ->where('user_id', $user->id)
        ->with(['battle:id,slug,title'])
        ->latest()
        ->paginate(20);
}
```

And update the `render()` method to include comments in the view data:
```php
return view('livewire.profile-page', [
    'user' => $user,
    'votes' => $this->loadVotes($user),
    'comments' => $this->loadComments($user),
]);
```

- [ ] **Step 4: Replace the Comments partial**

Replace `resources/views/livewire/profile/tabs/comments.blade.php`:
```blade
<div class="mt-3">
    @if ($comments->isEmpty())
        <div class="mx-4 rounded-xl border border-dashed border-white/10 p-6 text-center text-sm text-white/50">
            {{ __('profile.comments_empty') }}
        </div>
    @else
        <ul class="space-y-2 px-4">
            @foreach ($comments as $comment)
                <li class="rounded-xl border border-white/5 bg-white/[0.03] p-3">
                    <p class="text-sm text-white/90 whitespace-pre-line">{{ $comment->body }}</p>
                    <div class="mt-2 text-[11px] text-white/50 flex justify-between">
                        <a href="{{ route('battles.show', $comment->battle) }}" class="hover:text-white">
                            {{ __('profile.comments_on') }}
                            <span class="text-white/70">{{ $comment->battle->title }}</span>
                        </a>
                        <span>{{ $comment->created_at->diffForHumans(['parts' => 1, 'short' => true]) }}</span>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="px-4 mt-3">
            {{ $comments->links() }}
        </div>
    @endif
</div>
```

- [ ] **Step 5: Re-run the tests**

Run: `vendor/bin/pest tests/Feature/Profile/ProfilePageTest.php`
Expected: **PASS**.

- [ ] **Step 6: Commit**

```
git add app/Livewire/ProfilePage.php resources/views/livewire/profile/tabs/comments.blade.php tests/Feature/Profile/ProfilePageTest.php
git commit -m "ProfilePage: Comments tab"
```

---

## Task 10: Referrals tab — port ReferralPanel data and view

**Files:**
- Modify: `app/Livewire/ProfilePage.php`
- Modify: `resources/views/livewire/profile/tabs/referrals.blade.php`
- Test: `tests/Feature/Profile/ProfilePageTest.php`

- [ ] **Step 1: Write failing tests**

Append to `tests/Feature/Profile/ProfilePageTest.php`:
```php
test('referrals tab shows referral url, list and earned total', function () {
    $user = \App\Models\User::factory()->create(['referral_code' => 'TESTCODE']);
    $alice = \App\Models\User::factory()->create([
        'name' => 'Alice',
        'referred_by_id' => $user->id,
    ]);
    \App\Models\Transaction::factory()->create([
        'user_id' => $user->id,
        'type' => \App\Models\Transaction::TYPE_REFERRAL_REWARD,
        'amount' => 42,
    ]);

    $this->actingAs($user)
        ->get('/profile?tab=referrals')
        ->assertSee('?ref=TESTCODE', escape: false)
        ->assertSee('Alice')
        ->assertSee('42');
});

test('referrals tab shows empty state when no referrals', function () {
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)
        ->get('/profile?tab=referrals')
        ->assertSee(__('profile.referrals_empty'));
});
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/pest tests/Feature/Profile/ProfilePageTest.php`
Expected: **FAIL**.

- [ ] **Step 3: Add referral data to the component**

In `app/Livewire/ProfilePage.php`, add two more loaders:
```php
/**
 * @return \Illuminate\Database\Eloquent\Collection<int, User>
 */
private function loadReferrals(User $user)
{
    return User::query()
        ->where('referred_by_id', $user->id)
        ->orderByDesc('created_at')
        ->get(['id', 'name', 'email', 'created_at']);
}

private function loadReferralEarned(User $user): float
{
    return (float) Transaction::query()
        ->where('user_id', $user->id)
        ->where('type', Transaction::TYPE_REFERRAL_REWARD)
        ->sum('amount');
}
```

Update `render()`:
```php
return view('livewire.profile-page', [
    'user' => $user,
    'votes' => $this->loadVotes($user),
    'comments' => $this->loadComments($user),
    'referrals' => $this->loadReferrals($user),
    'referralUrl' => url('/?ref=' . $user->referral_code),
    'referralEarned' => $this->loadReferralEarned($user),
]);
```

- [ ] **Step 4: Replace the Referrals partial**

Replace `resources/views/livewire/profile/tabs/referrals.blade.php`:
```blade
<div class="mt-4 px-4 space-y-4">
    <section class="rounded-xl border border-white/5 bg-white/[0.03] p-4">
        <h3 class="text-sm font-semibold text-white">{{ __('profile.referrals_link_heading') }}</h3>
        <div class="mt-3 flex items-center gap-2"
             x-data="{ url: '{{ $referralUrl }}', copied: false }">
            <input readonly value="{{ $referralUrl }}"
                   class="flex-1 text-xs bg-navy-800 border border-white/10 rounded-md px-2 py-1.5 text-white/80">
            <button type="button"
                    @click="navigator.clipboard.writeText(url).then(() => { copied = true; setTimeout(() => copied = false, 1500); })"
                    class="text-xs font-semibold bg-vote-purple-to hover:opacity-90 rounded-md px-3 py-1.5 text-white">
                <span x-show="!copied">{{ __('profile.referrals_copy') }}</span>
                <span x-show="copied" x-cloak>{{ __('profile.referrals_copied') }}</span>
            </button>
        </div>
    </section>

    <section class="rounded-xl border border-white/5 bg-white/[0.03] p-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white">{{ __('profile.referrals_list_heading') }}</h3>
            <div class="text-right">
                <div class="text-[10px] uppercase tracking-wider text-white/50">{{ __('profile.referrals_earned') }}</div>
                <div class="text-base font-bold text-vote-purple-to">{{ number_format($referralEarned, 0) }}</div>
            </div>
        </div>

        @if ($referrals->isEmpty())
            <p class="mt-3 text-xs text-white/55">{{ __('profile.referrals_empty') }}</p>
        @else
            <ul class="mt-3 divide-y divide-white/5">
                @foreach ($referrals as $referral)
                    <li class="py-2 flex justify-between text-xs">
                        <span class="text-white/90">{{ $referral->name }}</span>
                        <span class="text-white/50">
                            {{ __('profile.referrals_joined', ['when' => $referral->created_at->diffForHumans()]) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
```

- [ ] **Step 5: Re-run the tests**

Run: `vendor/bin/pest tests/Feature/Profile/ProfilePageTest.php`
Expected: **PASS**.

- [ ] **Step 6: Commit**

```
git add app/Livewire/ProfilePage.php resources/views/livewire/profile/tabs/referrals.blade.php tests/Feature/Profile/ProfilePageTest.php
git commit -m "ProfilePage: Referrals tab"
```

---

## Task 11: Add username + bio to settings form

**Files:**
- Modify: `app/Http/Requests/ProfileUpdateRequest.php`
- Modify: `resources/views/profile/partials/update-profile-information-form.blade.php`
- Create: `tests/Feature/Profile/ProfileSettingsUpdateTest.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/Profile/ProfileSettingsUpdateTest.php`:
```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can set username and bio', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile/settings', [
            'name' => $user->name,
            'email' => $user->email,
            'username' => 'alice_99',
            'bio' => 'Just a test',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile/settings');

    $user->refresh();
    expect($user->username)->toBe('alice_99');
    expect($user->bio)->toBe('Just a test');
});

test('username must be unique', function () {
    User::factory()->create(['username' => 'taken']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/profile/settings')
        ->patch('/profile/settings', [
            'name' => $user->name,
            'email' => $user->email,
            'username' => 'taken',
        ])
        ->assertSessionHasErrors('username');
});

test('username rejects invalid characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/profile/settings')
        ->patch('/profile/settings', [
            'name' => $user->name,
            'email' => $user->email,
            'username' => 'has space',
        ])
        ->assertSessionHasErrors('username');
});

test('username may be unset by sending empty value', function () {
    $user = User::factory()->create(['username' => 'alice']);

    $this->actingAs($user)
        ->patch('/profile/settings', [
            'name' => $user->name,
            'email' => $user->email,
            'username' => '',
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->username)->toBeNull();
});

test('bio is saved and rendered on profile page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile/settings', [
            'name' => $user->name,
            'email' => $user->email,
            'bio' => "multi\nline bio",
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($user->fresh())
        ->get('/profile')
        ->assertSee('multi')
        ->assertSee('line bio');
});
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/pest tests/Feature/Profile/ProfileSettingsUpdateTest.php`
Expected: **FAIL** — validation does not know `username` / `bio`; they're not saved.

- [ ] **Step 3: Extend the validation rules**

Replace `app/Http/Requests/ProfileUpdateRequest.php`:
```php
<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'username' => [
                'nullable',
                'string',
                'min:3',
                'max:32',
                'regex:/^[a-zA-Z0-9_]+$/',
                Rule::unique(User::class, 'username')->ignore($this->user()->id),
            ],
            'bio' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('username') === '') {
            $this->merge(['username' => null]);
        }
        if ($this->input('bio') === '') {
            $this->merge(['bio' => null]);
        }
    }
}
```

- [ ] **Step 4: Add form fields to the settings form**

In `resources/views/profile/partials/update-profile-information-form.blade.php`, find the existing email field block. Right after the email block (and its verification `@if` block), add:
```blade
<div class="mt-4">
    <x-input-label for="username" :value="__('profile.username_label')" />
    <x-text-input id="username" name="username" type="text"
                  class="mt-1 block w-full"
                  value="{{ old('username', $user->username) }}" />
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('profile.username_help') }}</p>
    <x-input-error class="mt-2" :messages="$errors->get('username')" />
</div>

<div class="mt-4">
    <x-input-label for="bio" :value="__('profile.bio_label')" />
    <textarea id="bio" name="bio" rows="3"
              class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('bio', $user->bio) }}</textarea>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('profile.bio_help') }}</p>
    <x-input-error class="mt-2" :messages="$errors->get('bio')" />
</div>
```

- [ ] **Step 5: Re-run the tests**

Run: `vendor/bin/pest tests/Feature/Profile/ProfileSettingsUpdateTest.php`
Expected: **PASS**.

- [ ] **Step 6: Commit**

```
git add app/Http/Requests/ProfileUpdateRequest.php resources/views/profile/partials/update-profile-information-form.blade.php tests/Feature/Profile/ProfileSettingsUpdateTest.php
git commit -m "Profile settings: username and bio fields"
```

---

## Task 12: Avatar + banner uploads

**Files:**
- Modify: `app/Http/Requests/ProfileUpdateRequest.php`
- Modify: `app/Http/Controllers/ProfileController.php`
- Modify: `resources/views/profile/partials/update-profile-information-form.blade.php`
- Modify: `tests/Feature/Profile/ProfileSettingsUpdateTest.php`

- [ ] **Step 1: Write failing tests**

Append to `tests/Feature/Profile/ProfileSettingsUpdateTest.php`:
```php
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('user can upload an avatar', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile/settings', [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('me.png'),
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->avatar_path)->toStartWith('avatars/');
    Storage::disk('public')->assertExists($user->avatar_path);
});

test('user can upload a banner', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile/settings', [
            'name' => $user->name,
            'email' => $user->email,
            'banner' => UploadedFile::fake()->image('banner.png'),
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->banner_path)->toStartWith('banners/');
    Storage::disk('public')->assertExists($user->banner_path);
});

test('uploading a new avatar deletes the previous file', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    // First upload
    $this->actingAs($user)->patch('/profile/settings', [
        'name' => $user->name,
        'email' => $user->email,
        'avatar' => UploadedFile::fake()->image('first.png'),
    ]);
    $firstPath = $user->fresh()->avatar_path;
    Storage::disk('public')->assertExists($firstPath);

    // Second upload
    $this->actingAs($user->fresh())->patch('/profile/settings', [
        'name' => $user->name,
        'email' => $user->email,
        'avatar' => UploadedFile::fake()->image('second.png'),
    ]);

    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($user->fresh()->avatar_path);
});

test('non-image files are rejected', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/profile/settings')
        ->patch('/profile/settings', [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('avatar');
});
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/pest tests/Feature/Profile/ProfileSettingsUpdateTest.php`
Expected: **FAIL** — rules don't include `avatar` / `banner`; controller doesn't handle uploads.

- [ ] **Step 3: Extend validation rules**

In `app/Http/Requests/ProfileUpdateRequest.php`, add to the `rules()` array (before the closing bracket):
```php
'avatar' => ['nullable', 'image', 'max:2048'],
'banner' => ['nullable', 'image', 'max:2048'],
```

- [ ] **Step 4: Handle uploads in the controller**

Replace the `update` method in `app/Http/Controllers/ProfileController.php`:
```php
public function update(ProfileUpdateRequest $request): RedirectResponse
{
    $user = $request->user();
    $validated = $request->validated();

    $user->fill([
        'name' => $validated['name'],
        'email' => $validated['email'],
        'username' => $validated['username'] ?? null,
        'bio' => $validated['bio'] ?? null,
    ]);

    if ($user->isDirty('email')) {
        $user->email_verified_at = null;
    }

    if ($request->hasFile('avatar')) {
        if ($user->avatar_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar_path);
        }
        $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
    }

    if ($request->hasFile('banner')) {
        if ($user->banner_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->banner_path);
        }
        $user->banner_path = $request->file('banner')->store('banners', 'public');
    }

    $user->save();

    return Redirect::route('profile.settings')->with('status', 'profile-updated');
}
```

- [ ] **Step 5: Add file inputs to the form**

First, find the opening `<form ...>` tag in `resources/views/profile/partials/update-profile-information-form.blade.php` and ensure it has `enctype="multipart/form-data"`. If it doesn't, replace the form tag:
```blade
<form method="post" action="{{ route('profile.settings.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
```
Also update the route reference — the form currently posts to `route('profile.update')`; the new name is `profile.settings.update`. Fix that in the `action` attribute.

Then add these fields after the bio field from Task 11:
```blade
<div class="mt-4">
    <x-input-label for="avatar" :value="__('profile.avatar_label')" />
    <input id="avatar" name="avatar" type="file" accept="image/*"
           class="mt-1 block w-full text-sm text-gray-600 dark:text-gray-300" />
    <x-input-error class="mt-2" :messages="$errors->get('avatar')" />
</div>

<div class="mt-4">
    <x-input-label for="banner" :value="__('profile.banner_label')" />
    <input id="banner" name="banner" type="file" accept="image/*"
           class="mt-1 block w-full text-sm text-gray-600 dark:text-gray-300" />
    <x-input-error class="mt-2" :messages="$errors->get('banner')" />
</div>
```

- [ ] **Step 6: Re-run the tests**

Run: `vendor/bin/pest tests/Feature/Profile`
Expected: **PASS** across all profile tests.

- [ ] **Step 7: Commit**

```
git add app/Http/Requests/ProfileUpdateRequest.php app/Http/Controllers/ProfileController.php resources/views/profile/partials/update-profile-information-form.blade.php tests/Feature/Profile/ProfileSettingsUpdateTest.php
git commit -m "Profile settings: avatar and banner uploads"
```

---

## Task 13: Dark-theme reskin of settings page

**Files:**
- Modify: `resources/views/profile/edit.blade.php`

No new tests — existing `ProfileTest` covers the page renders OK.

- [ ] **Step 1: Replace the edit view wrapper**

Replace `resources/views/profile/edit.blade.php`:
```blade
<x-app-layout>
    <div class="max-w-2xl mx-auto px-4 py-6 space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold text-white">{{ __('profile.settings_title') }}</h1>
            <a href="{{ route('profile.edit') }}"
               class="text-xs text-white/60 hover:text-white">← {{ __('profile.title') }}</a>
        </div>

        <section class="rounded-xl border border-white/5 bg-white/[0.03] p-4 sm:p-6">
            @include('profile.partials.update-profile-information-form')
        </section>

        <section class="rounded-xl border border-white/5 bg-white/[0.03] p-4 sm:p-6">
            @include('profile.partials.update-password-form')
        </section>

        <section class="rounded-xl border border-red-500/10 bg-red-500/[0.04] p-4 sm:p-6">
            @include('profile.partials.delete-user-form')
        </section>
    </div>
</x-app-layout>
```

- [ ] **Step 2: Run the full suite**

Run: `make test`
Expected: no failures.

- [ ] **Step 3: Commit**

```
git add resources/views/profile/edit.blade.php
git commit -m "Profile settings: dark-theme reskin"
```

---

## Task 14: Remove /my-bets and /referrals — redirects

**Files:**
- Modify: `routes/web.php`
- Delete: `app/Livewire/MyBets.php`, `app/Livewire/ReferralPanel.php`, `resources/views/livewire/my-bets.blade.php`, `resources/views/livewire/referral-panel.blade.php`, `tests/Feature/Livewire/MyBetsTest.php`
- Delete (if present): `lang/en/my_bets.php`, `lang/ru/my_bets.php`
- Modify: `resources/views/layouts/navigation.blade.php`, `lang/en/nav.php`, `lang/ru/nav.php`
- Create: `tests/Feature/Navigation/ProfileRedirectsTest.php`

- [ ] **Step 1: Write failing redirect tests**

Create `tests/Feature/Navigation/ProfileRedirectsTest.php`:
```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('/my-bets redirects to /profile?tab=activity', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/my-bets')
        ->assertRedirect('/profile?tab=activity');
});

test('/referrals redirects to /profile?tab=referrals', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/referrals')
        ->assertRedirect('/profile?tab=referrals');
});
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/pest tests/Feature/Navigation/ProfileRedirectsTest.php`
Expected: **FAIL** — `/my-bets` and `/referrals` still hit their Livewire components (not redirects).

- [ ] **Step 3: Replace old routes with redirects**

In `routes/web.php` inside the `Route::middleware('auth')->group(...)` block, locate the two lines:
```php
Route::get('/referrals', ReferralPanel::class)->name('referrals');
Route::get('/my-bets', MyBets::class)->name('my-bets');
```

Replace them with:
```php
Route::get('/referrals', fn () => redirect('/profile?tab=referrals'))->name('referrals');
Route::get('/my-bets', fn () => redirect('/profile?tab=activity'))->name('my-bets');
```

Also remove the now-unused `use App\Livewire\MyBets;` and `use App\Livewire\ReferralPanel;` imports from the top of the file.

(Route names are kept — this way any existing `route('referrals')` / `route('my-bets')` call still resolves.)

- [ ] **Step 4: Run redirect tests**

Run: `vendor/bin/pest tests/Feature/Navigation/ProfileRedirectsTest.php`
Expected: **PASS**.

- [ ] **Step 5: Delete the obsolete components**

Run: `git rm app/Livewire/MyBets.php app/Livewire/ReferralPanel.php resources/views/livewire/my-bets.blade.php resources/views/livewire/referral-panel.blade.php tests/Feature/Livewire/MyBetsTest.php`

- [ ] **Step 6: Check for orphaned language files**

Run: `ls lang/en/my_bets.php lang/ru/my_bets.php 2>/dev/null`

If either file exists, delete it: `git rm lang/en/my_bets.php lang/ru/my_bets.php` (adjusting to whichever of the two actually exist).

- [ ] **Step 7: Prune the nav.php keys that only the removed pages used**

Look in `lang/en/nav.php` and `lang/ru/nav.php` for a `'referrals' => ...` entry. If the only user of `nav.referrals` was the navigation bar link you're about to delete (verify with `grep`):
```
grep -R "nav.referrals" resources lang app routes
```
If only `resources/views/layouts/navigation.blade.php` references it, remove the key from both locale files. Leave it if anything else uses it.

- [ ] **Step 8: Remove the desktop "Referrals" link from the top nav**

In `resources/views/layouts/navigation.blade.php`, delete the `@auth ... @endauth` block surrounding the desktop "Referrals" anchor (lines similar to):
```blade
@auth
    <a href="{{ route('referrals') }}" ... >{{ __('nav.referrals') }}</a>
@endauth
```

(The dropdown's `route('profile.edit')` link — further down in the same file — **stays** unchanged.)

- [ ] **Step 9: Run the full test suite**

Run: `make test`
Expected: all green. If `MyBetsTest` coverage gaps remain, fix the partial — otherwise proceed.

- [ ] **Step 10: Commit**

```
git add routes/web.php resources/views/layouts/navigation.blade.php lang/en/nav.php lang/ru/nav.php tests/Feature/Navigation/ProfileRedirectsTest.php
git commit -m "Retire /my-bets and /referrals — redirect into /profile tabs"
```

---

## Task 15: Final verification — pint, stan, full suite

**Files:** none (formatting / analysis pass).

- [ ] **Step 1: Pint**

Run: `make pint`
Expected: reports files formatted or no changes. If files changed, proceed — we'll commit them.

- [ ] **Step 2: PHPStan**

Run: `make ws` then inside the container `npm run stan`
(npm script passes `--memory-limit=512M`; direct `make stan` default is 128M and will OOM on `CastVoteAction`.)
Expected: no errors beyond the baseline. If a new error appears in code you just added, fix it inline. If an unrelated error appears — unlikely — stop and investigate.

- [ ] **Step 3: Full test suite**

Run: `make test`
Expected: all green, no warnings.

- [ ] **Step 4: Manual smoke (optional but recommended)**

1. `make up`
2. Open http://versus.local, log in as `admin@versus.test` / `password`.
3. Visit `/profile` — expect the new page with banner, avatar placeholder, `@username-fallback`, hardcoded 352/128/2450, name from DB, "Architect of Reality" title.
4. Click each tab — Activity / Versus Creation / Comments / Referrals.
5. Click EDIT — expect `/profile/settings` in the dark theme.
6. Set `username = admin_dev`, `bio = hello`, upload any PNG as avatar and banner, save.
7. Back on `/profile`, verify handle is `@admin_dev`, bio appears, avatar and banner render.
8. Visit `/my-bets` and `/referrals` manually — expect redirects.

- [ ] **Step 5: Commit formatting / analysis fixes if any**

```
git add -A
git diff --cached --quiet || git commit -m "Format and static-analysis pass"
```

(The `|| git commit ...` form is a no-op when there is nothing staged.)

---

## Notes

- **Storage link in dev:** the running stack likely already has `storage/app/public` linked to `public/storage`. If not, run `make art CMD="storage:link"` once. Tests don't need it — they use `Storage::fake('public')`.
- **UserFactory:** the new columns are all nullable, so no factory changes are required. If follow-up tests want default values, add `fake()->unique()->userName()` to an explicit state, don't add it to the default state (keeps existing tests that don't care about username predictable).
- **i18n coverage:** every user-facing string in the new partials flows through `__('profile.*')`. If a string was missed during review, add it to **both** locale files.

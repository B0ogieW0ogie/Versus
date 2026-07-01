# Design: «Newbie» rank + orange admin nicknames

**Date:** 2026-07-01
**Status:** Approved (design), pending spec review

## Problem

There is no rank system in Versus yet — no `rank` column, no rank constants, no
role-based nickname coloring. The profile page shows a hardcoded title
"Architect" (`profile.title_architect`) identical for everyone. We want a minimal
foundation now: every regular user reads as **Newbie**, and admin accounts get an
**orange nickname everywhere**. The full leveling/ranking system is deferred until
its own design exists.

## Decisions (from brainstorming)

- **Rank is computed on the fly** — no new DB column. `rank = is_admin ? admin : newbie`.
- **Display = nickname color only** (no "Newbie/Admin" badge next to names). Admin
  nick → orange; newbie → the existing default color of each spot.
- **Profile:** replace the hardcoded "Architect" title with the rank label
  (Admin / Newbie), colored accordingly.
- **Coverage: everywhere** — all seven nickname render spots (see below).
- **Orange:** `text-orange-400` (distinct from `amber-300` used for warnings).

## Model changes — `app/Models/User.php`

Add enum-like constants + helpers (matches the codebase convention of `const`
strings for state, e.g. `Battle::STATUS_ACTIVE`):

```php
const RANK_NEWBIE = 'newbie';
const RANK_ADMIN  = 'admin';

public function rank(): string
{
    return $this->is_admin ? self::RANK_ADMIN : self::RANK_NEWBIE;
}

public function rankLabel(): string
{
    return __('profile.rank_' . $this->rank());
}
```

Color is a view concern and lives in the Blade component, not the model.

## Reusable Blade component — `<x-user-name>`

New file `resources/views/components/user-name.blade.php`. Single source of truth
for nickname color so "orange everywhere" stays consistent.

Props:
- `:user` — the User (or any object exposing `name` + `is_admin`/`rank()`).
- `class` — base classes for the spot (size/weight, and the default/newbie color).

Behavior: renders a `<span>` with the passed `class`; for admins it **appends**
`text-orange-400` (and the span markup places it after any base text color so it
wins). Newbie keeps the base color unchanged.

```blade
@props(['user', 'class' => ''])
@php($isAdmin = $user->is_admin)
<span {{ $attributes->class([$class, 'text-orange-400' => $isAdmin]) }}>{{ $user->name }}</span>
```

Surrounding `<a>`/wrappers, sizes, and links stay in each view — the component
only owns the name text + color.

## Render spots to update (all 7)

| Spot | File | Notes |
|------|------|-------|
| Feed | `resources/views/components/feed/event-card.blade.php:46` | `$actor->name` |
| Leaderboard | `resources/views/livewire/leaderboard.blade.php:80` | `$row->name` — `$row` must expose `is_admin` (verify the query selects it) |
| Connections | `resources/views/livewire/connections-page.blade.php:26` | `$person->name` |
| Profile header | `resources/views/livewire/profile-page.blade.php:58` | `$user->name`, currently `text-vote-purple-to` |
| Profile title | `resources/views/livewire/profile-page.blade.php:97` | replace `title_architect` → `rankLabel()`; admin orange, newbie muted `text-white/50` |
| Comments | `resources/views/components/comment-thread/item.blade.php:78` | `$comment->user->name` |
| Sidebar | `resources/views/livewire/sidebar-widgets.blade.php:47` | `$p->name` |
| Referrals | `resources/views/livewire/profile/tabs/referrals.blade.php:32` | `$referral->name` |

For each: swap the `{{ $x->name }}` (and its color class) for
`<x-user-name :user="$x" class="<existing base classes minus color>" />`.

**Open risk:** the leaderboard / connections / referrals rows may be projected
DTOs or query results that don't carry `is_admin`. The plan must verify each
source loads `is_admin` (or the full User) before the component can read it; add
it to the select/`with()` where missing.

## i18n — both locales

Add to `lang/en/profile.php` and `lang/ru/profile.php`:

- `rank_newbie` → EN "Newbie" / RU "Новичок"
- `rank_admin`  → EN "Admin"  / RU "Админ"

Keep the old `title_architect` key or remove it if unused after the profile
change (verify no other references first).

## Tests (Pest)

- `User::rank()` returns `RANK_ADMIN` for an admin, `RANK_NEWBIE` otherwise.
- `User::rankLabel()` returns the translated label for each.
- Blade render smoke test: `<x-user-name>` output contains `text-orange-400` for
  an admin user and does not for a normal user.

## Out of scope

- Any real leveling logic, thresholds, XP, or additional ranks — deferred to a
  future dedicated design.
- Storing rank in the DB (revisit when the real system is designed).
- Filament admin UI changes.

## Verification gate

`make pint && make stan && make test` before claiming done.

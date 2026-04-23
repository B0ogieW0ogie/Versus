# Profile Page — Design (V1)

**Date:** 2026-04-23
**Scope:** visual shell for the user's own profile page, matching the supplied mockup. Anything not yet modelled in the domain (social graph, RP, titles, notifications) is hardcoded; we only plumb through fields the DB already has or that we add as simple columns on `users`.

## Context

The current `/profile` route points at the default Breeze edit form (name / email / password). The supplied mockup is a richer mobile-first profile screen with banner, avatar, bio, stats (Subscribers / Following / RP), tabs (Activity / Versus Creation / Comments / Referrals), and a header with notification + menu icons.

Building every subsystem implied by the mockup (follow graph, ranking points, achievements/titles, notifications, public profiles) is multiple independent epics. This cycle ships the **visual shell** against the data we already have, and hardcodes the rest so the UI looks as designed and those subsystems can be wired in later without re-doing layout.

Related existing work we are consolidating into this page:
- `MyBets` Livewire component at `/my-bets` (user's votes with active/settled tabs) → moves into the **Activity** tab.
- `ReferralPanel` Livewire component at `/referrals` (copyable link + referred users + total earned) → moves into the **Referrals** tab.

## Routes and components

| Route | Change | Handler |
|---|---|---|
| `GET /profile` | **Repurposed** — was Breeze edit form, now the new profile page. | `App\Livewire\ProfilePage` |
| `GET /profile/settings` | **New** — destination of the `EDIT` button. Shows the existing Breeze partials (name/email, password, delete) plus new `username` / `bio` / `avatar` / `banner` fields. | `ProfileController@edit` (existing, re-mounted) |
| `PATCH /profile/settings` | **New path** for the existing update action. | `ProfileController@update` |
| `DELETE /profile/settings` | **New path** for the existing delete action. | `ProfileController@destroy` |
| `GET /referrals` | **Removed.** Redirect `301 → /profile?tab=referrals`. | redirect closure |
| `GET /my-bets` | **Removed.** Redirect `301 → /profile?tab=activity`. | redirect closure |

Bottom-nav tab "Profile" already points at `route('profile.edit')`. The **route name `profile.edit`** is reassigned to the new page so the nav keeps working; the settings form gets new names `profile.settings` / `profile.settings.update` / `profile.settings.destroy`. (Alternative — introduce a new `profile.show` name — was considered; reusing `profile.edit` is less churn for links already in templates.)

Public profiles (`/u/{username}`) are explicitly **out of scope** for this cycle.

## Data model

### Migration: `users_add_profile_fields`

Add four nullable columns to `users`:

| Column | Type | Notes |
|---|---|---|
| `username` | `string(32) unique nullable` | User-chosen handle, shown as `@username`. Fallback in UI when null: `@user{id}`. |
| `bio` | `text nullable` | Free-form, max 500 chars enforced at form level. |
| `avatar_path` | `string(255) nullable` | Path under `storage/app/public/avatars/`. |
| `banner_path` | `string(255) nullable` | Path under `storage/app/public/banners/`. |

No data backfill required (all nullable). Unique index on `username` (Postgres + SQLite both support `UNIQUE` on nullable columns with multiple NULLs — verified OK for the test SQLite path too).

`User` model: add these to `Fillable`, add accessors `avatarUrl()` / `bannerUrl()` that return the `Storage::url(...)` or `null`.

### Hardcoded display values

These come from Blade / component constants, **not** DB:

| Value | Where | Source |
|---|---|---|
| Subscribers count | profile header | literal `352` |
| Following count | profile header | literal `128` |
| RP total | under avatar | literal `2,450` |
| Title ("Architect of Reality") | next to name, purple accent | Blade constant; same English string in both locales |
| Notification bell icon | header right | `disabled`, tooltip `coming_soon` |
| Kebab menu icon | header right | `disabled`, tooltip `coming_soon` |
| "Versus Creation" tab content | body | static "Coming soon" empty state |

## Page layout

Single Livewire component `App\Livewire\ProfilePage` + view `resources/views/livewire/profile-page.blade.php`. Content width capped to `max-w-2xl mx-auto` on `sm:`; mobile-first like the rest of the app.

Blocks top to bottom:

1. **Sticky header** (mobile): title "Profile" on the left, bell + kebab on the right (both disabled). No back arrow — this is a root nav tab.
2. **Banner** — full content width, `aspect-[16/7]`. If `banner_path` null → placeholder (`<x-icon.image-placeholder>` centered on a neutral background).
3. **Header row** under banner:
   - Avatar — 96px circle, `-mt-10`, ring to separate from banner. Fallback → generic user SVG.
   - Two stat columns — big number above small label ("352 Subscribers", "128 Following"). Non-clickable.
   - `EDIT` button on the right → `route('profile.settings')`.
4. **RP pill** under avatar — `<x-icon.trophy>` + `2,450 RP`.
5. **Name + handle + title** — `text-2xl font-bold` name, then `@handle · TitleText` on one line (flex-wrap for narrow screens).
6. **Bio** — `<p class="whitespace-pre-line">`, hidden if empty.
7. **Tab bar** — four text tabs (Activity / Versus Creation / Comments / Referrals) with an underline under the active tab, plus a disabled trophy icon button framed on the right as a visual filter affordance. Active tab controlled by `#[Url] public string $tab = 'activity'`.
8. **Tab content** — single `@switch($tab)` rendering one partial:
   - `livewire.profile.tabs.activity`
   - `livewire.profile.tabs.creation`
   - `livewire.profile.tabs.comments`
   - `livewire.profile.tabs.referrals`

### Tab contents

**Activity.** Single chronological list of the user's votes (no active/settled sub-tabs — simpler than the current `MyBets`). Each row:
- Small A-vs-B visual (reuse existing diagonal card at thumbnail scale)
- Battle title
- "You voted: {side_label}"
- "Amount: {amount} VRS"
- Right column: status badge (`WIN` green / `LOSE` red / `ACTIVE` neutral), net amount (`+payout` / `-amount` / none), relative time (`diffForHumans`)

Badge and net-amount computation reuse the logic from the current `MyBets::statusFor()` / `netAmountFor()` — lift those helpers into `ProfilePage` (or a dedicated `VoteStatus` value object if extraction is clean; not blocking the scope). Pagination: 20 per page via `WithPagination`.

**Versus Creation.** Static empty state (icon + "Coming soon" text). Tab is switchable so the four-tab layout stays symmetric in the URL/UX, but the body is a constant partial.

**Comments.** `Comment::where('user_id', $userId)->with('battle:id,slug,title')->latest()->paginate(20)`. Each row: truncated body, below it a link "on: {battle.title}" pointing at `battles.show`, relative time.

**Referrals.** Copyable `/?ref=CODE` link, list of referred users (name, joined date), total earned (`SUM` of `transactions` rows with `type = referral_reward`). Same data as the current `ReferralPanel`; the partial adapts the existing markup to fit inside the tab.

## Settings form (`/profile/settings`)

Extends the existing Breeze partial `profile.partials.update-profile-information-form`:

- **Existing:** `name`, `email` (+ email-verification redirect flow — unchanged).
- **New `username`:** `nullable|string|min:3|max:32|regex:/^[a-zA-Z0-9_]+$/` + `Rule::unique('users', 'username')->ignore($user->id)`. Empty → `null`.
- **New `bio`:** `nullable|string|max:500`, textarea.
- **New `avatar` file input:** `nullable|image|max:2048` (2 MB). Stored at `storage/app/public/avatars/{uuid}.{ext}` via `Storage::disk('public')->putFileAs(...)`. On replace, the previous `avatar_path` file is `Storage::disk('public')->delete(...)`-ed. No crop / no resize.
- **New `banner` file input:** same as avatar but under `banners/`.

`ProfileUpdateRequest` is updated with these rules. `ProfileController::update` reads `$request->validated()`, handles the two optional uploads, saves the user. The "Update Password" and "Delete Account" partials stay untouched.

Visual pass: reskin the settings page to match the app's dark layout (it's still Breeze light-mode today). No redesign — same form, same fields, just consistent styling under `layouts.app`. This lands as a small separate commit within the feature branch.

`storage:link` must exist locally; add a check to the README's dev-setup section. CI / test runs use `Storage::fake('public')` so no real link needed.

## i18n

New translation file `lang/{en,ru}/profile.php`:

```
subscribers, following, edit, rp, tab_activity, tab_creation,
tab_comments, tab_referrals, coming_soon, bio_placeholder,
activity_empty, comments_empty, you_voted, amount_label,
badge_win, badge_lose, badge_active
```

Title string ("Architect of Reality") is **not** translated — same English literal in both locales by design (treated as a stylised badge, not UI copy).

## Tests

Pest / Feature, SQLite in-memory, `RefreshDatabase` on each class.

### `tests/Feature/Profile/ProfilePageTest.php`

- guest is redirected to login
- shows own name, username and bio
- falls back to `@user{id}` when username is null
- shows the hardcoded title, RP, subscribers and following numbers
- activity tab lists user votes with win / lose / active badge and net amount
- comments tab lists the user's comments with a link to the battle
- referrals tab shows the referral URL, referred users, and total earned
- creation tab shows the coming-soon placeholder
- `?tab=comments` makes Comments the active tab on first render
- EDIT button links to `route('profile.settings')`

### `tests/Feature/Profile/ProfileSettingsUpdateTest.php`

(Extends the existing `ProfileUpdateTest` from Breeze; new cases:)

- user can set and change `username`
- `username` must be unique across users
- `username` rejects characters outside `[a-zA-Z0-9_]`
- `bio` is saved and properly escaped when rendered on the profile
- user can upload an avatar (via `UploadedFile::fake()->image(...)` with `Storage::fake('public')`)
- user can upload a banner (same pattern)
- uploading a new avatar deletes the previously stored file

### Redirects

One-liner smoke in a navigation / routing feature test:

- `GET /referrals` → `302` to `/profile?tab=referrals`
- `GET /my-bets` → `302` to `/profile?tab=activity`

Not covered (intentional):
- hardcoded constant values (RP / subscribers / following / title) — asserting constants tests nothing.
- disabled icons (bell / kebab / trophy filter) beyond one test that the "Coming soon" text is visible in the creation tab.
- image resize / crop — not implemented.

## Out of scope

- Social graph (real follow / unfollow relations, public follower lists)
- Real RP / ranking system
- Achievements / titles system
- Notification centre / bell dropdown
- Public profile pages at `/u/{username}`
- Cropping / resizing uploaded images
- Desktop-specific layout beyond `max-w-2xl` centering

## Files touched (summary)

```
database/migrations/<ts>_users_add_profile_fields.php              NEW
app/Models/User.php                                                 edit — fillable + accessors
app/Livewire/ProfilePage.php                                        NEW
resources/views/livewire/profile-page.blade.php                     NEW
resources/views/livewire/profile/tabs/activity.blade.php            NEW
resources/views/livewire/profile/tabs/creation.blade.php            NEW
resources/views/livewire/profile/tabs/comments.blade.php            NEW
resources/views/livewire/profile/tabs/referrals.blade.php           NEW
app/Http/Controllers/ProfileController.php                          edit — handle username/bio/avatar/banner
app/Http/Requests/ProfileUpdateRequest.php                          edit — new rules
resources/views/profile/edit.blade.php                              edit — dark layout + new fields
resources/views/profile/partials/update-profile-information-form.blade.php  edit
routes/web.php                                                      edit — new settings routes, redirects, remove /referrals, /my-bets
app/Livewire/MyBets.php                                             DELETE
app/Livewire/ReferralPanel.php                                      DELETE
resources/views/livewire/my-bets.blade.php                          DELETE
resources/views/livewire/referral-panel.blade.php                   DELETE
lang/en/profile.php                                                 NEW
lang/ru/profile.php                                                 NEW
tests/Feature/Profile/ProfilePageTest.php                           NEW
tests/Feature/Profile/ProfileSettingsUpdateTest.php                 edit/rename of existing ProfileUpdateTest
```

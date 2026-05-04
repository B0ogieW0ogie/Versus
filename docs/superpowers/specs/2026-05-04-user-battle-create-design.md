# User-created battles — Design

**Date:** 2026-05-04  
**Scope:** Allow any authenticated user to create a battle from the public app. Battles are **published immediately** as `active` (no admin approval loop). Short-term UX: after create, show a modal with a loader and fixed copy **"AI is checking your battle..."** for **5 seconds** (emulated check), then redirect to the new battle. A **single Livewire method** completes the screening step (today: set DB marker + redirect; later: swap body for real AI / job). Light DB hook for future AI. **No** HTTP rate limiter in this iteration.

## Context

- Battles are created today only via Filament (`BattleResource` / `BattleForm`).
- `battles.created_by_id` (nullable FK → `users`) and `Battle::creator()` already exist; seed/admin rows set this where appropriate.
- A stub `App\Livewire\BattleCreate` and related route work may already exist in the branch; this spec is the source of truth for behaviour.

## Approach (chosen)

| Option | Idea | Verdict |
|--------|------|--------|
| 1 | Emulation mostly in the browser (modal + timer), no long PHP `sleep` | **Yes** — primary UX |
| 2 | Small DB column updated when “screening” completes | **Yes** — nullable `ai_screened_at` (timestamp), set only from the dedicated completion method |
| 3 | `pending_ai` status hidden from listings until pass | **No** — product choice was immediate visibility (variant A) |

## Routes and auth

- **New** authenticated route, e.g. `GET /battles/create` → `App\Livewire\BattleCreate` wrapped in `auth` middleware (same stack as other app pages).
- Unauthenticated users hitting the URL are redirected to login (Breeze default behaviour).

## Data model

### Migration: `battles_add_ai_screened_at`

Add one nullable column:

| Column | Type | Notes |
|--------|------|--------|
| `ai_screened_at` | `timestamp` nullable | Set when the (emulated or future real) AI screening step completes successfully. Null means screening not completed or user abandoned the flow after create. |

Add to `Battle` model: cast datetime, fillable if mass-assigned from a controlled path, or set only inside the action/completion method (prefer **not** exposing to generic mass assignment from request arrays).

**V1 semantics:** Emulated screening always “passes”; we do not block listing or voting on null `ai_screened_at`. When real AI exists, product rules can tighten (separate spec).

## Form fields (user subset)

Mirror a **subset** of `BattleForm` / domain rules; everything else is server-defaulted:

| User-visible | Notes |
|----------------|-------|
| `title` | Required; drives auto `slug` (unique; append suffix if collision). |
| `description` | Optional. |
| `side_a_label`, `side_b_label` | Required. |
| `side_a_subtitle`, `side_b_subtitle` | Optional, same max length as admin. |
| `side_a_image`, `side_b_image` | Optional file uploads, same storage disk/conventions as admin. |
| `opens_at`, `closes_at` | Same semantics as admin (validation: closes after opens, reasonable bounds — reuse or align with existing battle validation). |
| `category_id` | Optional `Select` from `Category` ordered like admin. |

**Server-only on create:**

- `status` = `Battle::STATUS_ACTIVE`
- `total_pool` = `0`
- `is_sponsored` = `false`, `sponsor_handle` = null
- `winning_side` = null, `settled_at` = null
- `created_by_id` = `auth()->id()`

Admins retain full Filament CRUD including fields users cannot set.

## UX: AI check modal (emulation)

1. User submits valid form → persist battle as above (`ai_screened_at` remains **null** until step 2).
2. Response leaves the user on the same page with a **modal** open: blocking overlay, **spinner/loader**, body text exactly: `AI is checking your battle...` (literal English for this iteration; i18n can wrap the same string in `__('battle.ai_checking')` later if product wants RU).
3. **5 seconds** elapse (client-side timer, e.g. Livewire `$this->js(...)` + `setTimeout`, or Alpine bound to Livewire state). No `sleep()` on the initial create request.
4. Timer invokes **one** Livewire method (e.g. `completeAiScreening(int $battleId)`):
   - Authorize: battle exists, `created_by_id` === current user (abort 403 otherwise).
   - Idempotent: if `ai_screened_at` already set, skip write.
   - Set `ai_screened_at` = `now()` (emulated success path).
   - Redirect to public battle show route for that `slug` or id (match existing `BattleShow` URL pattern).

If the user closes the tab before step 4, the battle remains active with `ai_screened_at` null — acceptable for V1.

## Future real AI (non-goals for this spec)

- Replace the body of `completeAiScreening` with dispatching a job, calling an HTTP API, or branching on outcome — **without** changing the modal contract from the user’s perspective where possible.
- Failure paths (reject battle, flag for admin) are **not** specified here.

## Security

- Standard `auth` + **policy or inline gate** on `completeAiScreening`: only the creator may complete screening for that battle id.
- Create handler: `$request->user()` for `created_by_id`; validate only whitelisted input keys (no `status`, `total_pool`, `is_sponsored`, etc. from client).

## Rate limiting

Explicitly **out of scope** for this iteration (no `RateLimiter` on create route).

## Testing (Pest)

- Authenticated user POSTs/Livewire-submits minimal valid payload → battle row `status = active`, `created_by_id` set, `total_pool = 0`, sponsor fields false/null.
- Calling `completeAiScreening` for own battle sets `ai_screened_at` and returns redirect to show.
- Calling `completeAiScreening` for another user’s battle → 403.
- Guest cannot hit create URL without redirect to login.

## Files (expected touchpoints)

- `routes/web.php` — auth route registration.
- `app/Livewire/BattleCreate.php` + `resources/views/livewire/battle-create.blade.php` — form, modal, wire to completion method.
- `app/Models/Battle.php` — new attribute if needed.
- New migration `*_add_ai_screened_at_to_battles_table.php`.
- `lang/en/battle.php` (+ `lang/ru/battle.php` if i18n key added immediately) — optional key for modal copy.
- `tests/Feature/...` — new feature test class for create + screening completion + 403 case.
- Nav link / i18n for “Create battle” already partially present in branch — align labels with this spec.

## Self-review checklist

- [x] No `TBD` placeholders left for agreed scope.
- [x] Immediate `active` publish consistent with “variant A”.
- [x] 5s delay and exact modal string specified.
- [x] Single completion method + `ai_screened_at` hook documented.
- [x] Creator-only authorization on completion path explicit.

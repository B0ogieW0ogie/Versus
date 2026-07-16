# Design: Notification bell on mobile + faster polling

**Date:** 2026-07-16
**Status:** Approved (design), pending spec review
**Follows:** [2026-07-16-notification-bell-design.md](2026-07-16-notification-bell-design.md)

## Problem

The notification bell shipped inside the header's `hidden sm:flex` group (it
inherited the placeholder button's wrapper), so it is invisible below the `sm`
breakpoint — the whole feature does not exist for mobile users. Two blockers,
not one: simply un-hiding the bell is not enough, because the dropdown panel is
`absolute right-0 w-80` — a fixed 320px anchored to the bell's right edge. The
bell is not at the screen edge on mobile (the balance dropdown sits to its
right), so the panel would overflow the left edge of the viewport (~30px at
390px wide, worse at 360px and below).

Separately: the 60s poll interval chosen in the original design makes the ding
feel disconnected from the event that caused it.

## Decisions (from brainstorming)

- **Placement: header, next to search.** Not a 6th bottom-nav tab — the tab bar
  is a `grid-cols-5` with a centered FAB; a 6th column would squeeze labels
  below the legible width at 360px and push the FAB off-center.
- **Mobile dropdown: full-width fixed panel** under the header, not a bottom
  sheet. A sheet would mean two display modes to maintain for the same list.
- **Messages placeholder stays desktop-only** — it is a `coming_soon` stub;
  there is no reason to surface it on mobile.
- **Poll interval: 60s → 15s.** Still `wire:poll.visible`, so nothing polls
  while the tab is hidden; the request is a `COUNT` over the user's own
  indexed morph relation.

## Header — `resources/views/layouts/navigation.blade.php`

The `@auth` wrapper `<div class="hidden sm:flex items-center gap-2">` (holding
the bell and the Messages button) becomes `<div class="flex items-center
gap-2">`, and `hidden sm:inline-flex` moves onto the Messages `<button>`
itself.

DOM order is unchanged, so both layouts keep their current arrangement:

- Mobile: `search · bell · balance`
- Desktop (`sm+`): `search · locale · bell · messages · balance` — identical to today

## Dropdown — `resources/views/livewire/notification-bell.blade.php`

The panel's positioning classes change from:

```
absolute right-0 mt-2 w-80
```

to a mobile-first pair — a fixed, viewport-width panel below the `h-16` header
on small screens, reverting to today's anchored dropdown at `sm+`:

```
fixed top-[4.5rem] inset-x-3 sm:absolute sm:top-auto sm:inset-x-auto sm:right-0 sm:mt-2 sm:w-80
```

`top-[4.5rem]` = 72px = the 64px (`h-16`) header plus the 8px gap `mt-2` gives
at `sm+`. Everything else on the panel (`rounded-xl`, `bg-navy-800`, border,
`z-50`, `max-h-96` scroll area) is untouched.

## Poll interval — same file

`wire:poll.visible.60s="refreshCount"` → `wire:poll.visible.15s="refreshCount"`.

No component change: `refreshCount()` already dispatches `notification-ding`
only when the unread count grows, so a faster interval cannot produce extra
sounds — only a sooner one.

## What does not change

The component (`app/Livewire/NotificationBell.php`), the notification classes,
the sending actions, the sound logic, mark-as-read semantics, and the lang
files are all untouched. This is a presentation-layer change plus one interval
literal.

## Verification

CSS placement is not observable from Livewire/Blade tests, which assert
component state and rendered strings — so the real gate is a browser check:

- **390px viewport:** bell visible in the header between search and balance;
  badge renders; opening the dropdown shows a panel that fits the viewport with
  no horizontal overflow (assert `document.documentElement.scrollWidth <=
  window.innerWidth`) and no clipping of the message text.
- **1280px viewport:** header order and dropdown appearance identical to before
  the change (anchored, 320px wide).
- **Regression:** `make art CMD="test --filter=NotificationBellTest"` and
  `--filter=Navigation` stay green — they cover the markup contract (badge
  count, mark-as-read, auth-only rendering).

## Out of scope

- Bottom-nav notification entry, a dedicated notifications page.
- Touch-target resizing of the header icons (the bell keeps `p-2`, matching the
  neighbouring search button).
- Websockets / real-time push — still deferred.

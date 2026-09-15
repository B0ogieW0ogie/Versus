# Design: Challenges — core (sub-project 1)

**Date:** 2026-09-15
**Status:** Approved (design), pending spec review

## Context

Versus pivots. **Challenges** become the main mode: a vertical short-video feed
(Reels / Shorts / TikTok style) where anyone posts a video challenge, viewers
answer it with their own video, and viewers vote for the best video. The token
economy (battles, stakes, pool, wallet, signup bonus) stays in the code but is
switched off in the UI. Participation is free; status comes from reputation
instead of tokens.

Goal of the launch: test the **engagement-loop theory** — see challenge → accept
→ record answer → share → a new person arrives by the link → answers or creates
their own. Audience comes from blogging on every social network; content
recording starts once challenges ship.

### Product decisions (from brainstorming)

| Topic | Decision |
|---|---|
| Battles / tokens | Hidden behind a flag, code and data kept |
| VOTE | Vote for the video on the current slide (original or response); challenge has a deadline; most votes wins |
| Recording | Own in-browser recording screen, gallery upload as fallback |
| Co-authorship | One video, two credits ("@responder in reply to @author"), shown in both profiles; no stitching, no author approval |
| Votes per viewer | **One per challenge**, can be moved until the deadline; no voting for own video; registered users only |
| Deadline | Author picks 24h / 3d / 7d; after it answers and votes close, winner announced, challenge archived |
| Top-10 carousel | 7 slots by votes + 3 rotating slots for low-impression responses, then an "All responses (N)" card |
| Reputation | Points for results and participation (win, top-3, answer, vote received, popular challenge); never expires; votes from unverified accounts give no reputation |
| Reputation effect | Status only (rank, badge, leaderboard); gates nothing |
| Home tab | Showcase (trending, ending soon, categories, weekly top); landing screen is the Challenges feed |
| Moderation | Post-moderation: reports, auto-hide after N reports, admin queue |
| Storage | Laravel filesystem on **local disk** now; S3 later is a driver change |
| Private duel | 1-vs-1: author calls out an @username or shares an invite link; only the called user may answer; voting is public; no answer by deadline → author wins |
| Editor | Trim + music from an admin-managed royalty-free library; final file assembled by server ffmpeg; no stickers/text/effects |
| Analytics | First-party events table + share attribution + loop funnel dashboard in Filament |
| Feed order | Non-personalised score: freshness + 24h activity + ending-soon boost; seen items sink |

Defaults: video ≤ 60 s, vertical 9:16, transcoded to 720p H.264/AAC MP4, poster
from the first frame. Likes are separate from votes (unlimited, no reputation).
Comments are a slim flat thread per video. `@username` becomes mandatory to create a
challenge or call someone out.

### Decomposition

| # | Sub-project | Scope |
|---|---|---|
| **1** | **Challenges core** (this spec) | models, gallery upload, transcoding, feed + carousel, vote, deadline/winner, create/respond form, My Challenges, battles flag |
| 2 | Recording screen | in-browser camera, timer, countdown |
| 3 | Editor | trim, music library in Filament, ffmpeg assembly |
| 4 | Duels 1-vs-1 | @username call-out, invite link, walkover win |
| 5 | Reputation | points, ranks, leaderboard instead of tokens |
| 6 | Moderation | reports, auto-hide, Filament queue |
| 7 | Loop analytics | events, share attribution, funnel dashboard |
| 8 | Home showcase | trending, ending soon, weekly top |

**1, 6 and 7 must ship before content recording starts** — without moderation it
is unsafe to bring traffic, without analytics the test says nothing. 2 and 3
improve UX but are not needed to test the theory. Each sub-project gets its own
spec → plan → implementation cycle.

### Out of scope for sub-project 1

Duels (`mode`, opponent, invite), reports / `hidden_at`, music and trim params,
reputation, in-browser recording, Home showcase. No columns are added "for later";
each sub-project brings its own migration.

## Existing code this builds on

- Comments are bound to `battle_id` (`comments` table) — needs a second target.
- `users.username` exists but is nullable
  (`2026_04_23_113030_add_profile_fields_to_users_table.php`).
- `queue-worker` and `scheduler` containers already run
  ([docker-compose.yml](../../../docker-compose.yml)); **ffmpeg is not installed**
  in [.docker/php/Dockerfile](../../../.docker/php/Dockerfile).
- `/feed` is taken by the activity feed (`FeedPage`), so the new feed lives at
  `/challenges`.
- Notifications go through the database channel and `NotificationBell`.

## 1. Data model

### `challenges`

| column | notes |
|---|---|
| `id`, `slug` (unique) | |
| `user_id` | author, FK users |
| `title` | string(100) |
| `rules` | string(500), may contain `#hashtags` (highlighted, not stored separately) |
| `duration` | `24h` \| `3d` \| `7d` |
| `ends_at` | nullable; set when the original video becomes ready, **not** at upload |
| `status` | `processing` → `active` → `closed`; or `failed` |
| `winner_entry_id` | nullable FK challenge_entries |
| `closed_at` | nullable |
| `reminder_sent_at` | nullable; "1 hour left" dedupe |
| `feed_score` | float, default 0; recomputed every minute |
| `entries_count`, `votes_count` | denormalised caches |
| timestamps | |

Constants on the model: `STATUS_*`, `DURATION_*`. Canonical check
`Challenge::isOpen(): bool` = `status === active && now() < ends_at` — used by
voting and responding (mirrors `Battle::isOpenForVoting()`).

### `challenge_entries`

Any video of a challenge — **original and responses in one table**, because a vote
targets "the video on the slide" and the original can win.

| column | notes |
|---|---|
| `id`, `challenge_id`, `user_id` | |
| `is_original` | bool |
| `status` | `processing` \| `ready` \| `failed` |
| `source_path` | private upload, deleted after transcoding |
| `video_path`, `poster_path` | public disk, UUID names |
| `duration_ms` | |
| `failure_reason` | nullable string |
| `votes_count`, `likes_count`, `impressions_count` | caches |
| `submitted_at` | moment the user pressed Publish (deadline check uses this) |
| timestamps | |

Unique `(challenge_id, user_id)` — one video per user per challenge (the author's
row is the original). Index `(challenge_id, status, votes_count)`.

### `challenge_votes`

`id, user_id, challenge_id, entry_id, timestamps`, **unique (`user_id`,
`challenge_id`)**. Moving a vote updates the row and adjusts both entries'
`votes_count` in the same transaction.

### `challenge_participants`

`id, user_id, challenge_id, accepted_at`, unique (`user_id`, `challenge_id`).
Written by "Accept challenge" and automatically on responding.

### `entry_likes`

`id, user_id, entry_id, timestamps`, unique (`user_id`, `entry_id`).

### `challenge_views`

`id, user_id, challenge_id, viewed_at`, unique (`user_id`, `challenge_id`). Used
only to sink seen challenges in the feed. Guests: session array.

### `comments`

`battle_id` becomes nullable; add nullable `challenge_entry_id` FK. Exactly one
is set (enforced in the posting actions). `CommentThread` stays battle-only — it
is coupled to token-priced likes, sides and stake support. Video comments use a
slim flat thread in the feed's comments sheet (list + post via
`PostEntryCommentAction`; no likes, replies or notifications in sub-project 1).
Battle-only comment queries (`ProfilePage`, `FeedService`) filter
`whereNotNull('battle_id')`.

### Models

`Challenge`, `ChallengeEntry`, `ChallengeVote`, `ChallengeParticipant`,
`EntryLike`, `ChallengeView`.

### Config — `config/versus.php`

```php
'battles_enabled' => env('VERSUS_BATTLES_ENABLED', false),

'challenges' => [
    'durations' => ['24h' => 24 * 60, '3d' => 3 * 24 * 60, '7d' => 7 * 24 * 60], // minutes
    'max_video_seconds' => 60,
    'max_upload_mb' => 100,
    'upload_chunk_mb' => 5,
    'daily_create_limit' => 10,
    'carousel_top_by_votes' => 7,
    'carousel_fresh_slots' => 3,
    'reminder_minutes_before' => 60,
    'feed' => [
        'freshness_half_life_hours' => 24,
        'activity_window_hours' => 24,
        'weight_activity' => 1.0,
        'weight_freshness' => 1.0,
        'ending_soon_hours' => 6,
        'weight_ending_soon' => 0.5,
    ],
],
```

### Battles flag

`versus.battles_enabled = false` hides battle routes from navigation, the wallet,
the signup bonus (`CreditSignupBonusAction` becomes a no-op) and redirects `/` to
`/challenges`. Battle routes and data stay intact; flipping the flag restores them.

## 2. Video pipeline

### Upload

- Chunked upload (5 MB chunks) to a dedicated controller, not Livewire uploads —
  mobile connections drop and a single 100 MB request fails. A dropped upload
  resumes from the last chunk.
  - `POST /uploads` → `{upload_id}` (UUID, bound to user in cache, 24 h TTL)
  - `PUT /uploads/{id}/chunks/{n}` → appends
  - `POST /uploads/{id}/complete` → validates total size, returns `upload_id`
- Before uploading, the browser reads duration from a `<video>` element; > 60 s
  shows "Video must be up to 60 s" and stops. (Trim arrives in sub-project 3.)
- Chunks and assembled source live on the private disk:
  `storage/app/private/uploads/{upload_id}/`.

### Processing — `ProcessEntryVideo` job

Behind `App\Services\Video\VideoTranscoder` (interface) with
`FfmpegVideoTranscoder`; tests bind `FakeVideoTranscoder`.

1. `ffprobe`: a video stream exists, duration ≤ 60 s (+0.5 s tolerance), size
   ≤ 100 MB. The client is never trusted.
2. `ffmpeg`: scale to fit 720×1280 with padding, H.264 (CRF 23) + AAC,
   `-movflags +faststart`.
3. Poster: frame at 0.5 s as JPG.
4. Success: write `video_path`, `poster_path`, `duration_ms`, status `ready`,
   delete source.
   - Original → challenge `active`, `ends_at = now() + duration`.
   - Response → increment `challenges.entries_count`, notify the challenge author.
5. Failure (after 3 tries, timeout 300 s): notify the uploader with the reason.
   - Original → entry `failed` with `failure_reason`, challenge `failed`.
   - Response → entry row and files are **deleted**, so the unique
     `(challenge_id, user_id)` does not block a retry; the My Challenges card
     returns to "Upload response".

### Serving

Ready files go to the `public` disk as `videos/{uuid}.mp4` and
`posters/{uuid}.jpg`. nginx serves them directly with Range support and long
cache headers. All paths go through `Storage::disk(...)`.

### Edge cases

- Response submitted before the deadline but transcoded after it: accepted
  (`submitted_at` counts).
- Cleanup: scheduled daily `challenges:cleanup-uploads` removes upload dirs older
  than 24 h and entries stuck in `processing` older than 24 h.
- Infra: add `ffmpeg` to `.docker/php/Dockerfile` (shared by `queue-worker`);
  nginx `client_max_body_size` ≥ chunk size (set 10M).

## 3. Feed and carousel — `/challenges`

`ChallengeFeed` Livewire component + Alpine. When battles are disabled, `/`
redirects here. Guests may browse; like, vote, accept and comment redirect to
login.

### Vertical feed

- One full-height slide per challenge above the bottom nav; CSS scroll-snap.
  Batches of 5, loaded as the user nears the end.
- Only `active` challenges. Order: unseen before seen, then `feed_score` desc.
- `feed_score` is computed in PHP by `challenges:score-feed` (scheduled every
  minute): freshness decay + activity (entries + votes) in the last 24 h +
  ending-soon boost, weights from config.
- A challenge is marked viewed when its slide stays visible ≥ 1 s.

### Horizontal carousel

- Slide 0: original, orange pill **Challenge**.
- Slides 1–10: responses, purple pill **Response N/10**.
- Last slide: "All responses (N)" card → grid sheet of all ready responses.
- Composition (`ChallengeCarousel` service, built on load): top 7 ready responses
  by `votes_count` (tie: earlier `submitted_at`) + 3 random from the remaining
  ready responses with the lowest `impressions_count`; no duplicates; fewer
  responses → fewer slides.
- An impression counts when a video is visible ≥ 1 s; impressions are sent in a
  batch (`POST /challenges/impressions`), not per video.

### Playback

- Only the visible video plays. Starts muted (autoplay policy); a tap unmutes,
  and unmuted stays for the session.
- At most 3 `<video>` elements have `src` at once (current + neighbours) —
  iOS Safari memory limit.

### Slide UI

- Top-left pill (Challenge / Response N/10); top-right notification bell.
- Right rail for the current entry: like (count), comments (sheet with
  `CommentThread` for this entry), share, `•••` (copy link only in
  sub-project 1). Share uses the Web Share API, fallback copies
  `/c/{slug}?entry={id}`.
- Bottom-left: clickable avatar + display name → profile.
- Countdown line "⏱ 05:12:33" above the title (not in the mockup, added for
  urgency).
- Title + short description are one block; tap → sheet with full rules,
  hashtags, countdown, author.
- Two big buttons in one row with a diagonal split (as in battles):
  - **Accept challenge** (orange):
    - default → add participant, open Record/Upload choice (Upload only here);
    - already responded → "My response", scrolls to it;
    - own challenge → disabled "Your challenge".
  - **Vote** (purple), for the current slide's entry:
    - voted for this entry → "Your vote ✓";
    - voted for another entry → confirm "Move your vote?";
    - own entry → disabled.
- Closed challenge (reached via deep link): the two buttons become a
  "Winner @user" plate; the winning entry shows a badge.
- First-time "Swipe left / right to see more responses" hint, shown once:
  a flag on the user for logged-in users, `localStorage` for guests.

### Deep link

`/c/{slug}?entry={id}` opens the feed on that challenge and entry, even if the
entry is outside the top 10 and even if the challenge is closed. This is the loop
entry point from social networks.

## 4. Create and respond

One screen, two modes. Bottom nav hidden.

### New challenge — `/challenges/create` (auth + verified)

- Header: back arrow + title. If anything is filled or uploaded, back opens
  "Leave without saving?"; `beforeunload` warns too.
- Video block: **Upload** only (Record comes in sub-project 2). Progress bar;
  instant local preview via object URL.
- Fields: Title\* (≤ 100, counter), Rules & conditions\* (≤ 500, counter,
  hashtags highlighted), Deadline\* (sheet: 24 h / 3 d / 7 d).
- Audience / Privacy block is **not shown** in sub-project 1 — all challenges are
  public (switch + duel block come in sub-project 4).
- No `@username` → an inline "Choose your @username" field above Publish;
  required.
- **Publish** → `App\Actions\Challenges\CreateChallengeAction`:
  - validates fields, upload ownership, daily limit (10/day);
  - in `DB::transaction` creates challenge (`processing`) + original entry
    (`processing`, `submitted_at = now()`), moves the source from the upload dir;
  - dispatches `ProcessEntryVideo` **after commit**;
  - transcoding starts only on Publish, so abandoned drafts cost no CPU.
- Redirect to My Challenges with toast "Processing video, we'll notify you".
- Failed transcoding → challenge `failed`; the card offers "Upload video again",
  which reopens the form with title and rules kept.

### Response — `/c/{slug}/respond` (auth + verified)

Reached via Accept → Upload in the feed, or Upload response in My Challenges.

- Title and rules prefilled and **read-only**; a live countdown replaces the
  deadline picker.
- **Publish** → `App\Actions\Challenges\SubmitResponseAction`, in
  `DB::transaction` with `lockForUpdate` on the challenge:
  - `Challenge::isOpen()`;
  - not the author;
  - no existing entry from this user;
  - creates entry (`processing`, `submitted_at = now()`), upserts participant;
  - dispatches `ProcessEntryVideo` after commit.
- Deadline passed while filling the form → "Challenge is over" error; the upload
  is deleted.

## 5. My Challenges — `/my-challenges` (auth)

Third bottom-nav tab (replaces Battles). Bottom nav: Home · Challenges · ＋ ·
My Challenges · Profile.

### List

- Header "My Challenges", right side "N active".
- Own challenges + accepted ones, one list, no tabs.
- Order: active by `ends_at` asc → processing / failed → closed by `closed_at`
  desc. Pages of 20. Tapping a card opens it in the feed.
- Empty state: "You haven't accepted any challenges yet" + "Go to challenges".

### Card

- Left: poster of the original; if the viewer has an entry, a translucent check in
  a green circle over it.
- Right: title, "by @author", rules clamped to 3 lines.
- Under the poster: live countdown (client-side Alpine); same row right: status
  pill `Active` (teal) / `Processing` (grey) / `Failed` (red) / `Over` (purple).
- Buttons:

| State | Buttons |
|---|---|
| Accepted, active, no entry | **Upload response** (full width; Record joins in sub-project 2) |
| Accepted, entry processing | grey plate "Response processing…" |
| Accepted, entry ready | "My response" → feed at own entry |
| Own, active | "Responses (N)" |
| Own, failed | "Upload video again" |
| Closed (any) | countdown replaced by "winner: @user" (or "No votes"); button "Results" → feed at winning entry |

- `•••` on an accepted challenge without an entry: "Remove from my challenges"
  (deletes the participant row). No menu otherwise.

## 6. Deadline and results

### Closing

- `challenges:close-due` runs every minute (next to `battles:settle-due` in
  [routes/console.php](../../../routes/console.php)) and calls
  `App\Actions\Challenges\CloseChallengeAction` for each `active` challenge with
  `ends_at <= now()`.
- `CloseChallengeAction`, in `DB::transaction` with `lockForUpdate` on the
  challenge; no-op unless still `active` and past `ends_at` (idempotent):
  - recounts votes per entry from `challenge_votes` (caches not trusted);
  - winner = entry with the most votes; tie → earlier `submitted_at` (the
    original wins ties); **zero votes → no winner**;
  - sets `closed`, `winner_entry_id`, `closed_at`; drops out of the feed;
  - after commit dispatches `ChallengeClosed` event (reputation and analytics
    subscribe later without touching this action).
- Entries still transcoding at close are not awaited — voting is closed, they
  cannot have votes; they appear in "All responses" when ready.

### Last-second race

`CastChallengeVoteAction` locks the same challenge row and checks
`Challenge::isOpen()` (time, not just status — the close command runs once a
minute). Vote and close serialize on one lock: a vote at 23:59:59 is either
counted or rejected, never lost. `SubmitResponseAction` uses the same rule.

### Vote action — `App\Actions\Challenges\CastChallengeVoteAction`

In `DB::transaction`, lock challenge, lock the target entry:
- open, registered, target entry `ready` and belongs to the challenge, not own;
- no existing vote → insert, increment entry and challenge `votes_count`;
- existing vote for another entry → update, decrement old entry, increment new
  (challenge total unchanged);
- existing vote for the same entry → no-op.

### Other actions

`AcceptChallengeAction`, `LeaveChallengeAction` (only without an entry),
`ToggleEntryLikeAction`, `RecordImpressionsAction`.

### Notifications (database channel, existing bell)

| Class | Recipient | Trigger |
|---|---|---|
| `ChallengePublished` | author | original ready |
| `EntryProcessingFailed` | uploader | transcoding failed |
| `ChallengeResponseReceived` | author | response ready |
| `ResponsePublished` | responder | response ready |
| `ChallengeEndingSoon` | participants without an entry | 60 min before `ends_at`, once (`reminder_sent_at`), sent by `challenges:close-due` |
| `ChallengeResults` | everyone with an entry | closed: "You won!" / "Challenge over, @user won" / "No votes" |

`NotificationBell::url()` gains a branch for `challenge_slug` / `entry_id` keys
→ `/c/{slug}?entry={id}`.

### i18n

All new strings in `lang/en/challenges.php` and `lang/ru/challenges.php`.

## Testing (Pest, SQLite, `FakeVideoTranscoder`)

- **Actions:**
  - create: validation, daily limit, username required, job dispatched after
    commit;
  - respond: closed challenge, past deadline, own challenge, duplicate response;
  - vote: first vote, move vote (counters), same entry no-op, own entry, not
    ready entry, after deadline;
  - close: winner, tie → earlier entry, original wins tie, zero votes, re-run is
    a no-op, `ChallengeClosed` dispatched.
- **Job:** ready → challenge active with `ends_at` from ready time; failed →
  challenge failed + notification; response ready → author notified.
- **Carousel:** 7 by votes + 3 lowest impressions, no duplicates, < 10 responses.
- **Feed score:** unseen before seen, closed excluded.
- **Livewire:** feed button states (guest, own, voted, moved, closed); My
  Challenges card states and ordering; guest redirected to login.
- **Flag:** `battles_enabled=false` hides battles, wallet, signup bonus; `/`
  redirects to `/challenges`.
- **Gate:** `make pint && make stan && make test`.

# Challenges Core Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship sub-project 1 of the Challenges pivot — video challenges with responses, one-vote-per-challenge PvP, deadlines and winners, a vertical short-video feed, the create/respond form and My Challenges — with battles hidden behind a flag.

**Architecture:** Domain logic lives in `App\Actions\Challenges\*` single-invoke classes wrapped in `DB::transaction` + `lockForUpdate` on the challenge row. Video goes browser → chunked upload controller → private disk → `ProcessEntryVideo` queue job → `VideoTranscoder` (ffmpeg; faked in tests) → public disk. The feed is a Livewire component whose actions are `#[Renderless]` and return arrays; Alpine renders slides from JSON built by `ChallengeFeedPresenter`, so video playback and scroll position survive every interaction.

**Tech Stack:** Laravel 13 · Livewire 4 · Alpine (bundled) · Tailwind 3 · PostgreSQL 16 (dev/prod) / SQLite `:memory:` (tests) · Pest 4 runner with PHPUnit-style test classes · ffmpeg/ffprobe via `Illuminate\Support\Facades\Process`.

**Spec:** [docs/superpowers/specs/2026-09-15-challenges-core-design.md](../specs/2026-09-15-challenges-core-design.md)

## Global Constraints

- All commands run through Docker: `make ws` then `php artisan test --filter=...`; full gate `make pint && make stan && make test`.
- Tests are PHPUnit-style classes (`class XTest extends TestCase { use RefreshDatabase; ... }`) under `tests/Feature/Challenges/`; `tests/Pest.php` does **not** apply `RefreshDatabase` globally.
- No Postgres-only SQL in code that runs under tests.
- Commit straight to `main` after each task; end every commit message with `Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>`.
- Economic/tunable numbers live in `config/versus.php` under `challenges` — never inline.
- Every user-facing string goes through `__('challenges.key')` and exists in both `lang/en/challenges.php` and `lang/ru/challenges.php`.
- Model state uses `const` strings (`Challenge::STATUS_ACTIVE`, `ChallengeEntry::STATUS_READY`) — no literals.
- Video limits: ≤ 60 s (+0.5 s tolerance server-side), ≤ 100 MB, chunks of 5 MB, output 720×1280 H.264 CRF 23 + AAC with `+faststart`, poster at 0.5 s.
- Durations: `24h`, `3d`, `7d`. `ends_at` is set when the original video becomes ready.
- One vote per user per challenge (movable until `ends_at`); no vote for own entry; one entry per user per challenge.
- Carousel: 7 responses by votes + 3 from the lowest-impression rest.
- Daily create limit: 10 non-failed challenges per rolling 24 h.
- `challenges.entries_count` counts **ready responses only** (the original is excluded).

### Refinement vs. spec (agreed during planning)

`CommentThread` is coupled to the token economy (comment likes move balance, sides A/B, stake support), so video comments get a slim flat thread inside the feed (list + post, no likes/replies) on the same `comments` table via `challenge_entry_id`. The spec's comments paragraph is updated in Task 11.

## File Map

| Path | Responsibility |
|---|---|
| `config/versus.php` | `battles_enabled` flag + `challenges` tunables |
| `lang/{en,ru}/challenges.php` | all challenge strings |
| `database/migrations/2026_09_15_100000_create_challenges_tables.php` | challenges, entries, votes, participants, likes, views, `users.swipe_hint_seen_at` |
| `database/migrations/2026_09_15_100100_add_challenge_entry_id_to_comments_table.php` | comments second target |
| `app/Models/{Challenge,ChallengeEntry,ChallengeVote,ChallengeParticipant,EntryLike,ChallengeView}.php` | models |
| `database/factories/{Challenge,ChallengeEntry}Factory.php` | factories |
| `app/Services/Video/UploadStore.php` | chunked upload bookkeeping on the `local` disk |
| `app/Http/Controllers/ChallengeUploadController.php` | upload HTTP API |
| `app/Services/Video/{VideoTranscoder,FfmpegVideoTranscoder,VideoProbe,VideoProcessingException}.php` | ffmpeg boundary |
| `tests/Fakes/FakeVideoTranscoder.php` | test double |
| `app/Jobs/ProcessEntryVideo.php` | probe → transcode → poster |
| `app/Actions/Challenges/*.php` | all state changes |
| `app/Notifications/Challenge*.php`, `EntryProcessingFailed.php`, `ResponsePublished.php` | bell notifications |
| `app/Events/ChallengeClosed.php` | hook for reputation/analytics |
| `app/Console/Commands/{CloseDueChallenges,ScoreChallengeFeed,CleanupChallengeUploads}Command.php` | scheduled work |
| `app/Services/Challenges/{FeedScorer,ChallengeCarousel,ChallengeFeedQuery,ChallengeFeedPresenter,MyChallengesQuery}.php` | read-side logic |
| `app/Livewire/{ChallengeFeed,ChallengeForm,MyChallenges}.php` + views | pages |
| `resources/js/challenges/{feed,upload}.js` | Alpine components |
| `app/Http/Middleware/EnsureBattlesEnabled.php` | battles flag gate |

---

### Task 1: Config, translations, signup-bonus flag

**Files:**
- Modify: `config/versus.php`
- Modify: `phpunit.xml`
- Modify: `.env.example`
- Create: `lang/en/challenges.php`, `lang/ru/challenges.php`
- Modify: `app/Actions/Users/CreditSignupBonusAction.php`
- Test: `tests/Feature/Challenges/BattlesFlagSignupBonusTest.php`

**Interfaces:**
- Produces: `config('versus.battles_enabled'): bool`, `config('versus.challenges.*')` (keys below), translation keys `challenges.*` used by every later task.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Challenges;

use App\Actions\Users\CreditSignupBonusAction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattlesFlagSignupBonusTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_signup_bonus_when_battles_disabled(): void
    {
        config(['versus.battles_enabled' => false]);
        $user = User::factory()->create(['balance' => 0]);

        app(CreditSignupBonusAction::class)($user);

        $this->assertSame(0, Transaction::where('user_id', $user->id)->count());
        $this->assertSame('0.00', $user->fresh()->balance);
    }

    public function test_signup_bonus_still_credited_when_battles_enabled(): void
    {
        config(['versus.battles_enabled' => true]);
        $user = User::factory()->create(['balance' => 0]);

        app(CreditSignupBonusAction::class)($user);

        $this->assertSame(1, Transaction::where('user_id', $user->id)->count());
    }

    public function test_challenge_translations_exist_in_both_locales(): void
    {
        $en = require lang_path('en/challenges.php');
        $ru = require lang_path('ru/challenges.php');

        $this->assertSame([], array_diff(array_keys($en), array_keys($ru)));
        $this->assertSame([], array_diff(array_keys($ru), array_keys($en)));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=BattlesFlagSignupBonusTest`
Expected: FAIL — first test credits a bonus; third fails with "Failed opening required .../challenges.php".

- [ ] **Step 3: Add config**

In `config/versus.php`, add right after `return [`:

```php
    /** Battles + token economy UI. Off for the Challenges launch; code and data stay. */
    'battles_enabled' => (bool) env('VERSUS_BATTLES_ENABLED', false),

    'challenges' => [
        'durations' => ['24h' => 24 * 60, '3d' => 3 * 24 * 60, '7d' => 7 * 24 * 60], // minutes
        'max_video_seconds' => 60,
        'max_upload_mb' => 100,
        'upload_chunk_mb' => 5,
        'daily_create_limit' => 10,
        'carousel_top_by_votes' => 7,
        'carousel_fresh_slots' => 3,
        'reminder_minutes_before' => 60,
        'comment_max_length' => 1000,
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

In `phpunit.xml` add inside `<php>` next to the other `<env>` lines (keeps every existing battle test on the old UI):

```xml
        <env name="VERSUS_BATTLES_ENABLED" value="true"/>
```

In `.env.example` add:

```
VERSUS_BATTLES_ENABLED=false
```

- [ ] **Step 4: Create translations**

`lang/en/challenges.php`:

```php
<?php

return [
    // Feed
    'pill_challenge' => 'Challenge',
    'pill_response' => 'Response :n/:total',
    'accept' => 'Accept challenge',
    'vote' => 'Vote',
    'your_vote' => 'Your vote ✓',
    'your_challenge' => 'Your challenge',
    'my_response' => 'My response',
    'move_vote_confirm' => 'Move your vote to this video?',
    'winner' => 'Winner :name',
    'winner_short' => 'winner: :name',
    'no_votes' => 'No votes',
    'all_responses' => 'All responses (:count)',
    'swipe_hint_title' => 'Swipe left / right',
    'swipe_hint_body' => 'to see more responses',
    'in_reply_to' => 'in reply to :name',
    'link_copied' => 'Link copied',
    'comments' => 'Comments',
    'comment_placeholder' => 'Add a comment…',
    'comment_send' => 'Send',
    'no_comments' => 'No comments yet',
    'feed_empty' => 'No active challenges yet. Create the first one!',
    'rules' => 'Rules & conditions',
    'close' => 'Close',
    'votes_count' => ':count votes',

    // Form
    'create_title' => 'Create Challenge',
    'respond_title' => 'Respond to challenge',
    'leave_confirm' => 'Leave without saving?',
    'leave' => 'Leave',
    'stay' => 'Stay',
    'upload' => 'Upload',
    'upload_hint' => 'Tap to upload video',
    'uploading' => 'Uploading… :percent%',
    'video_too_long' => 'Video must be up to :seconds s',
    'video_too_big' => 'Video must be up to :mb MB',
    'upload_failed' => 'Upload failed, try again',
    'field_title' => 'Challenge title',
    'field_title_placeholder' => 'e.g. Can you do this better?',
    'field_rules' => 'Rules & conditions',
    'field_rules_placeholder' => 'Describe the challenge, what to do, any specific rules… #hashtags',
    'field_deadline' => 'Time limit / Deadline',
    'select_time' => 'Select time',
    'duration_24h' => '24 hours',
    'duration_3d' => '3 days',
    'duration_7d' => '7 days',
    'time_left' => 'Time left',
    'field_username' => 'Choose your @username',
    'publish' => 'Publish challenge',
    'publish_response' => 'Publish response',
    'video_required' => 'Upload a video first',
    'processing_toast' => "Processing video, we'll notify you",

    // Validation / domain errors
    'title_required' => 'Enter a title up to 100 characters',
    'rules_required' => 'Describe the rules in up to 500 characters',
    'duration_required' => 'Pick a deadline',
    'daily_limit' => 'You can create up to :limit challenges per day',
    'username_required' => 'Choose a @username (3–32 letters, digits or _)',
    'username_taken' => 'This @username is taken',
    'not_open' => 'Challenge is over',
    'own_challenge' => "You can't respond to your own challenge",
    'already_responded' => 'You have already responded to this challenge',
    'cannot_vote_own' => "You can't vote for your own video",
    'entry_not_votable' => 'This video is not available',
    'invalid_upload' => 'Upload not found, please upload the video again',
    'cannot_leave_with_entry' => "You can't remove a challenge you have responded to",
    'comment_required' => 'Write something first',
    'cannot_retry' => 'This challenge cannot be re-uploaded',

    // My challenges
    'my_title' => 'My Challenges',
    'active_count' => ':count active',
    'my_empty' => "You haven't accepted any challenges yet",
    'go_to_challenges' => 'Go to challenges',
    'by' => 'by :name',
    'status_active' => 'Active',
    'status_processing' => 'Processing',
    'status_failed' => 'Failed',
    'status_closed' => 'Over',
    'upload_response' => 'Upload response',
    'response_processing' => 'Response processing…',
    'responses_count' => 'Responses (:count)',
    'upload_again' => 'Upload video again',
    'results' => 'Results',
    'remove_from_mine' => 'Remove from my challenges',
    'load_more' => 'Load more',

    // Processing failure reasons (stored as keys on the entry)
    'reason_no_video' => 'the file has no video track',
    'reason_too_long' => 'the video is longer than :seconds s',
    'reason_too_big' => 'the video is larger than :mb MB',
    'reason_generic' => 'we could not process this video',

    // Notifications
    'notif_published' => 'Your challenge “:title” is live',
    'notif_failed' => 'Video for “:title” failed: :reason',
    'notif_response_received' => ':name responded to your challenge “:title”',
    'notif_response_published' => 'Your response to “:title” is live',
    'notif_ending_soon' => '1 hour left to respond to “:title”',
    'notif_results_won' => 'You won “:title”!',
    'notif_results_over' => '“:title” is over, :name won',
    'notif_results_no_votes' => '“:title” is over with no votes',

    // Navigation
    'nav_challenges' => 'Challenges',
    'nav_my' => 'My Challenges',
];
```

`lang/ru/challenges.php`:

```php
<?php

return [
    // Feed
    'pill_challenge' => 'Челлендж',
    'pill_response' => 'Ответ :n/:total',
    'accept' => 'Принять челлендж',
    'vote' => 'Голосовать',
    'your_vote' => 'Ваш голос ✓',
    'your_challenge' => 'Ваш челлендж',
    'my_response' => 'Мой ответ',
    'move_vote_confirm' => 'Перенести голос на это видео?',
    'winner' => 'Победитель :name',
    'winner_short' => 'победитель: :name',
    'no_votes' => 'Нет голосов',
    'all_responses' => 'Все ответы (:count)',
    'swipe_hint_title' => 'Листайте влево / вправо',
    'swipe_hint_body' => 'чтобы увидеть другие ответы',
    'in_reply_to' => 'в ответ :name',
    'link_copied' => 'Ссылка скопирована',
    'comments' => 'Комментарии',
    'comment_placeholder' => 'Написать комментарий…',
    'comment_send' => 'Отправить',
    'no_comments' => 'Комментариев пока нет',
    'feed_empty' => 'Активных челленджей пока нет. Создайте первый!',
    'rules' => 'Правила и условия',
    'close' => 'Закрыть',
    'votes_count' => 'Голосов: :count',

    // Form
    'create_title' => 'Новый челлендж',
    'respond_title' => 'Ответ на челлендж',
    'leave_confirm' => 'Выйти без сохранения?',
    'leave' => 'Выйти',
    'stay' => 'Остаться',
    'upload' => 'Загрузить',
    'upload_hint' => 'Нажмите, чтобы загрузить видео',
    'uploading' => 'Загрузка… :percent%',
    'video_too_long' => 'Видео должно быть не длиннее :seconds с',
    'video_too_big' => 'Видео должно быть не больше :mb МБ',
    'upload_failed' => 'Не удалось загрузить, попробуйте ещё раз',
    'field_title' => 'Название челленджа',
    'field_title_placeholder' => 'Например: Сможешь лучше?',
    'field_rules' => 'Правила и условия',
    'field_rules_placeholder' => 'Опишите челлендж: что сделать, какие правила… #хештеги',
    'field_deadline' => 'Срок / дедлайн',
    'select_time' => 'Выберите срок',
    'duration_24h' => '24 часа',
    'duration_3d' => '3 дня',
    'duration_7d' => '7 дней',
    'time_left' => 'Осталось',
    'field_username' => 'Выберите @username',
    'publish' => 'Опубликовать челлендж',
    'publish_response' => 'Опубликовать ответ',
    'video_required' => 'Сначала загрузите видео',
    'processing_toast' => 'Обрабатываем видео, пришлём уведомление',

    // Validation / domain errors
    'title_required' => 'Введите название до 100 символов',
    'rules_required' => 'Опишите правила до 500 символов',
    'duration_required' => 'Выберите срок',
    'daily_limit' => 'Можно создать не больше :limit челленджей в сутки',
    'username_required' => 'Выберите @username (3–32 латинские буквы, цифры или _)',
    'username_taken' => 'Этот @username уже занят',
    'not_open' => 'Челлендж завершён',
    'own_challenge' => 'Нельзя отвечать на свой челлендж',
    'already_responded' => 'Вы уже ответили на этот челлендж',
    'cannot_vote_own' => 'Нельзя голосовать за своё видео',
    'entry_not_votable' => 'Это видео недоступно',
    'invalid_upload' => 'Загрузка не найдена, загрузите видео заново',
    'cannot_leave_with_entry' => 'Нельзя убрать челлендж, на который вы уже ответили',
    'comment_required' => 'Сначала напишите текст',
    'cannot_retry' => 'Этот челлендж нельзя перезагрузить',

    // My challenges
    'my_title' => 'Мои челленджи',
    'active_count' => 'Активных: :count',
    'my_empty' => 'Вы ещё не приняли ни одного челленджа',
    'go_to_challenges' => 'К челленджам',
    'by' => 'автор :name',
    'status_active' => 'Активен',
    'status_processing' => 'Обработка',
    'status_failed' => 'Ошибка',
    'status_closed' => 'Завершён',
    'upload_response' => 'Загрузить ответ',
    'response_processing' => 'Ответ обрабатывается…',
    'responses_count' => 'Ответы (:count)',
    'upload_again' => 'Загрузить видео заново',
    'results' => 'Итоги',
    'remove_from_mine' => 'Убрать из моих',
    'load_more' => 'Показать ещё',

    // Processing failure reasons
    'reason_no_video' => 'в файле нет видеодорожки',
    'reason_too_long' => 'видео длиннее :seconds с',
    'reason_too_big' => 'видео больше :mb МБ',
    'reason_generic' => 'не удалось обработать видео',

    // Notifications
    'notif_published' => 'Ваш челлендж «:title» опубликован',
    'notif_failed' => 'Видео для «:title» не обработано: :reason',
    'notif_response_received' => ':name ответил(а) на ваш челлендж «:title»',
    'notif_response_published' => 'Ваш ответ на «:title» опубликован',
    'notif_ending_soon' => 'Остался 1 час, чтобы ответить на «:title»',
    'notif_results_won' => 'Вы победили в «:title»!',
    'notif_results_over' => '«:title» завершён, победил(а) :name',
    'notif_results_no_votes' => '«:title» завершён без голосов',

    // Navigation
    'nav_challenges' => 'Челленджи',
    'nav_my' => 'Мои челленджи',
];
```

- [ ] **Step 5: Gate the signup bonus**

In `app/Actions/Users/CreditSignupBonusAction.php`, first line of `__invoke`:

```php
        if (! config('versus.battles_enabled')) {
            return;
        }
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --filter=BattlesFlagSignupBonusTest` → PASS. Then `php artisan test` → whole suite still green (phpunit env keeps battles on).

- [ ] **Step 7: Commit**

```bash
git add config/versus.php phpunit.xml .env.example lang/en/challenges.php lang/ru/challenges.php app/Actions/Users/CreditSignupBonusAction.php tests/Feature/Challenges/BattlesFlagSignupBonusTest.php
git commit -m "feat(challenges): config, translations and battles flag for signup bonus

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 2: Schema, models, factories

**Files:**
- Create: `database/migrations/2026_09_15_100000_create_challenges_tables.php`
- Create: `app/Models/Challenge.php`, `app/Models/ChallengeEntry.php`, `app/Models/ChallengeVote.php`, `app/Models/ChallengeParticipant.php`, `app/Models/EntryLike.php`, `app/Models/ChallengeView.php`
- Create: `database/factories/ChallengeFactory.php`, `database/factories/ChallengeEntryFactory.php`
- Modify: `app/Models/User.php` (fillable/cast `swipe_hint_seen_at`)
- Test: `tests/Feature/Challenges/ChallengeModelTest.php`

**Interfaces:**
- Produces:
  - `Challenge` consts `STATUS_PROCESSING|STATUS_ACTIVE|STATUS_CLOSED|STATUS_FAILED`, `DURATION_24H|DURATION_3D|DURATION_7D`; `isOpen(): bool`; `static durationMinutes(string $duration): int` (throws `InvalidArgumentException`); relations `user()`, `entries()`, `original()` (HasOne), `responses()` (HasMany, `is_original=false`), `votes()`, `participants()`, `winnerEntry()`.
  - `ChallengeEntry` consts `STATUS_PROCESSING|STATUS_READY|STATUS_FAILED`; `videoUrl(): ?string`, `posterUrl(): ?string`; relations `challenge()`, `user()`, `likes()`, `comments()`.
  - Factories: `Challenge::factory()` (active, 24h, ends in 1 day), states `processing()`, `closed()`, `failed()`, `withOriginal()`; `ChallengeEntry::factory()` (ready response), states `original()`, `processing()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeVote;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ChallengeModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_open_requires_active_status_and_future_deadline(): void
    {
        $this->assertTrue(Challenge::factory()->create()->isOpen());
        $this->assertFalse(Challenge::factory()->create(['ends_at' => now()->subSecond()])->isOpen());
        $this->assertFalse(Challenge::factory()->processing()->create()->isOpen());
        $this->assertFalse(Challenge::factory()->closed()->create()->isOpen());
    }

    public function test_duration_minutes_come_from_config(): void
    {
        $this->assertSame(1440, Challenge::durationMinutes(Challenge::DURATION_24H));
        $this->assertSame(10080, Challenge::durationMinutes(Challenge::DURATION_7D));

        $this->expectException(InvalidArgumentException::class);
        Challenge::durationMinutes('1y');
    }

    public function test_with_original_creates_a_ready_original_entry(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();

        $this->assertTrue($challenge->original->is_original);
        $this->assertSame($challenge->user_id, $challenge->original->user_id);
        $this->assertSame(ChallengeEntry::STATUS_READY, $challenge->original->status);
        $this->assertStringEndsWith('.mp4', (string) $challenge->original->videoUrl());
    }

    public function test_one_entry_per_user_per_challenge(): void
    {
        $challenge = Challenge::factory()->create();
        $user = User::factory()->create();
        ChallengeEntry::factory()->for($challenge)->for($user)->create();

        $this->expectException(QueryException::class);
        ChallengeEntry::factory()->for($challenge)->for($user)->create();
    }

    public function test_one_vote_per_user_per_challenge(): void
    {
        $challenge = Challenge::factory()->create();
        $a = ChallengeEntry::factory()->for($challenge)->create();
        $b = ChallengeEntry::factory()->for($challenge)->create();
        $voter = User::factory()->create();
        ChallengeVote::create(['user_id' => $voter->id, 'challenge_id' => $challenge->id, 'entry_id' => $a->id]);

        $this->expectException(QueryException::class);
        ChallengeVote::create(['user_id' => $voter->id, 'challenge_id' => $challenge->id, 'entry_id' => $b->id]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ChallengeModelTest`
Expected: FAIL with `Class "App\Models\Challenge" not found`.

- [ ] **Step 3: Migration**

`database/migrations/2026_09_15_100000_create_challenges_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 100);
            $table->string('rules', 500);
            $table->string('duration', 8);
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 16)->index();
            // No FK: challenge_entries is created below and references challenges.
            $table->unsignedBigInteger('winner_entry_id')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->double('feed_score')->default(0);
            $table->unsignedInteger('entries_count')->default(0);
            $table->unsignedInteger('votes_count')->default(0);
            $table->timestamps();

            $table->index(['status', 'ends_at']);
            $table->index(['status', 'feed_score']);
        });

        Schema::create('challenge_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('challenge_id')->constrained('challenges')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_original')->default(false);
            $table->string('status', 16);
            $table->string('source_path')->nullable();
            $table->string('video_path')->nullable();
            $table->string('poster_path')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('failure_reason', 64)->nullable();
            $table->unsignedInteger('votes_count')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('impressions_count')->default(0);
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['challenge_id', 'user_id']);
            $table->index(['challenge_id', 'status', 'votes_count']);
        });

        Schema::create('challenge_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('challenge_id')->constrained('challenges')->cascadeOnDelete();
            $table->foreignId('entry_id')->constrained('challenge_entries')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'challenge_id']);
            $table->index(['challenge_id', 'entry_id']);
        });

        Schema::create('challenge_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('challenge_id')->constrained('challenges')->cascadeOnDelete();
            $table->timestamp('accepted_at');

            $table->unique(['user_id', 'challenge_id']);
        });

        Schema::create('entry_likes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('entry_id')->constrained('challenge_entries')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'entry_id']);
        });

        Schema::create('challenge_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('challenge_id')->constrained('challenges')->cascadeOnDelete();
            $table->timestamp('viewed_at');

            $table->unique(['user_id', 'challenge_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('swipe_hint_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('swipe_hint_seen_at');
        });
        Schema::dropIfExists('challenge_views');
        Schema::dropIfExists('entry_likes');
        Schema::dropIfExists('challenge_participants');
        Schema::dropIfExists('challenge_votes');
        Schema::dropIfExists('challenge_entries');
        Schema::dropIfExists('challenges');
    }
};
```

- [ ] **Step 4: Models**

`app/Models/Challenge.php`:

```php
<?php

namespace App\Models;

use Database\Factories\ChallengeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property Carbon|null $ends_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $reminder_sent_at
 */
#[Fillable([
    'slug', 'user_id', 'title', 'rules', 'duration', 'ends_at', 'status',
    'winner_entry_id', 'closed_at', 'reminder_sent_at', 'feed_score',
    'entries_count', 'votes_count',
])]
class Challenge extends Model
{
    /** @use HasFactory<ChallengeFactory> */
    use HasFactory;

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_FAILED = 'failed';

    public const DURATION_24H = '24h';

    public const DURATION_3D = '3d';

    public const DURATION_7D = '7d';

    protected function casts(): array
    {
        return [
            'ends_at' => 'datetime',
            'closed_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'feed_score' => 'float',
            'entries_count' => 'integer',
            'votes_count' => 'integer',
            'winner_entry_id' => 'integer',
        ];
    }

    public static function durationMinutes(string $duration): int
    {
        $minutes = config('versus.challenges.durations.'.$duration);

        if (! is_int($minutes)) {
            throw new InvalidArgumentException("Unknown challenge duration [{$duration}].");
        }

        return $minutes;
    }

    /** Canonical "can people still respond and vote?" check. */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->ends_at !== null
            && now()->lt($this->ends_at);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ChallengeEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(ChallengeEntry::class);
    }

    /** @return HasOne<ChallengeEntry, $this> */
    public function original(): HasOne
    {
        return $this->hasOne(ChallengeEntry::class)->where('is_original', true);
    }

    /** @return HasMany<ChallengeEntry, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(ChallengeEntry::class)->where('is_original', false);
    }

    /** @return HasMany<ChallengeVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(ChallengeVote::class);
    }

    /** @return HasMany<ChallengeParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(ChallengeParticipant::class);
    }

    /** @return BelongsTo<ChallengeEntry, $this> */
    public function winnerEntry(): BelongsTo
    {
        return $this->belongsTo(ChallengeEntry::class, 'winner_entry_id');
    }
}
```

`app/Models/ChallengeEntry.php`:

```php
<?php

namespace App\Models;

use Database\Factories\ChallengeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property Carbon $submitted_at
 */
#[Fillable([
    'challenge_id', 'user_id', 'is_original', 'status', 'source_path', 'video_path',
    'poster_path', 'duration_ms', 'failure_reason', 'votes_count', 'likes_count',
    'impressions_count', 'submitted_at',
])]
class ChallengeEntry extends Model
{
    /** @use HasFactory<ChallengeEntryFactory> */
    use HasFactory;

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'is_original' => 'boolean',
            'submitted_at' => 'datetime',
            'duration_ms' => 'integer',
            'votes_count' => 'integer',
            'likes_count' => 'integer',
            'impressions_count' => 'integer',
        ];
    }

    public function videoUrl(): ?string
    {
        return $this->video_path ? Storage::disk('public')->url($this->video_path) : null;
    }

    public function posterUrl(): ?string
    {
        return $this->poster_path ? Storage::disk('public')->url($this->poster_path) : null;
    }

    /** @return BelongsTo<Challenge, $this> */
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<EntryLike, $this> */
    public function likes(): HasMany
    {
        return $this->hasMany(EntryLike::class, 'entry_id');
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'challenge_entry_id');
    }
}
```

> `comments()` points at a column added in Task 11; nothing calls it before then.

`app/Models/ChallengeVote.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'challenge_id', 'entry_id'])]
class ChallengeVote extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Challenge, $this> */
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    /** @return BelongsTo<ChallengeEntry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(ChallengeEntry::class, 'entry_id');
    }
}
```

`app/Models/ChallengeParticipant.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'challenge_id', 'accepted_at'])]
class ChallengeParticipant extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Challenge, $this> */
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }
}
```

`app/Models/EntryLike.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'entry_id'])]
class EntryLike extends Model {}
```

`app/Models/ChallengeView.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'challenge_id', 'viewed_at'])]
class ChallengeView extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['viewed_at' => 'datetime'];
    }
}
```

In `app/Models/User.php`: add `'swipe_hint_seen_at'` to the `#[Fillable([...])]` list and `'swipe_hint_seen_at' => 'datetime',` to `casts()`.

- [ ] **Step 5: Factories**

`database/factories/ChallengeFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Challenge>
 */
class ChallengeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $title = $this->faker->sentence(4);

        return [
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'user_id' => User::factory(),
            'title' => Str::limit($title, 100, ''),
            'rules' => $this->faker->sentence(12),
            'duration' => Challenge::DURATION_24H,
            'ends_at' => now()->addDay(),
            'status' => Challenge::STATUS_ACTIVE,
        ];
    }

    public function processing(): static
    {
        return $this->state(fn () => ['status' => Challenge::STATUS_PROCESSING, 'ends_at' => null]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => Challenge::STATUS_FAILED, 'ends_at' => null]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => Challenge::STATUS_CLOSED,
            'ends_at' => now()->subHour(),
            'closed_at' => now()->subMinutes(59),
        ]);
    }

    public function withOriginal(): static
    {
        return $this->afterCreating(function (Challenge $challenge): void {
            ChallengeEntry::factory()->original()->create([
                'challenge_id' => $challenge->id,
                'user_id' => $challenge->user_id,
                'status' => $challenge->status === Challenge::STATUS_PROCESSING
                    ? ChallengeEntry::STATUS_PROCESSING
                    : ChallengeEntry::STATUS_READY,
            ]);
        });
    }
}
```

`database/factories/ChallengeEntryFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChallengeEntry>
 */
class ChallengeEntryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'challenge_id' => Challenge::factory(),
            'user_id' => User::factory(),
            'is_original' => false,
            'status' => ChallengeEntry::STATUS_READY,
            'video_path' => "videos/{$uuid}.mp4",
            'poster_path' => "posters/{$uuid}.jpg",
            'duration_ms' => 15000,
            'submitted_at' => now(),
        ];
    }

    public function original(): static
    {
        return $this->state(fn () => ['is_original' => true]);
    }

    public function processing(): static
    {
        return $this->state(fn () => [
            'status' => ChallengeEntry::STATUS_PROCESSING,
            'video_path' => null,
            'poster_path' => null,
            'duration_ms' => null,
            'source_path' => 'entries/pending/source',
        ]);
    }
}
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --filter=ChallengeModelTest` → PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_09_15_100000_create_challenges_tables.php app/Models database/factories tests/Feature/Challenges/ChallengeModelTest.php
git commit -m "feat(challenges): schema, models and factories

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 3: Chunked upload store and API

**Files:**
- Create: `app/Services/Video/UploadStore.php`
- Create: `app/Http/Controllers/ChallengeUploadController.php`
- Modify: `routes/web.php` (inside the existing `Route::middleware(['auth', 'verified'])->group(...)`)
- Test: `tests/Feature/Challenges/ChallengeUploadTest.php`

**Interfaces:**
- Produces:
  - `UploadStore::start(User $user): string` (UUID)
  - `UploadStore::appendChunk(User $user, string $uploadId, int $index, UploadedFile $chunk): void`
  - `UploadStore::status(User $user, string $uploadId): array{next_chunk: int, size: int, completed: bool}`
  - `UploadStore::complete(User $user, string $uploadId): void`
  - `UploadStore::claim(User $user, string $uploadId): string` — relative path on the `local` disk of the finished source; throws `ValidationException` (`upload` => `challenges.invalid_upload`) when missing/foreign/incomplete.
  - `UploadStore::discard(string $uploadId): void`
  - `UploadStore::dir(string $uploadId): string` → `uploads/{id}`
  - Routes: `POST /challenge-uploads` (`challenge-uploads.start`), `POST /challenge-uploads/{upload}/chunks` (`challenge-uploads.chunk`), `GET /challenge-uploads/{upload}` (`challenge-uploads.status`), `POST /challenge-uploads/{upload}/complete` (`challenge-uploads.complete`). JSON; `start` returns `{upload_id, chunk_bytes}`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Challenges;

use App\Models\User;
use App\Services\Video\UploadStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ChallengeUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_chunks_are_appended_in_order_and_claimable_after_complete(): void
    {
        $user = User::factory()->create();

        $id = $this->actingAs($user)->postJson(route('challenge-uploads.start'))
            ->assertOk()
            ->assertJsonPath('chunk_bytes', 5 * 1024 * 1024)
            ->json('upload_id');

        $this->postJson(route('challenge-uploads.chunk', $id), ['index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c0', 'AAA')])
            ->assertOk()->assertJsonPath('next_chunk', 1);
        // A resent chunk (network retry) is ignored, not duplicated.
        $this->postJson(route('challenge-uploads.chunk', $id), ['index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c0', 'AAA')])
            ->assertOk()->assertJsonPath('next_chunk', 1);
        $this->postJson(route('challenge-uploads.chunk', $id), ['index' => 1, 'chunk' => UploadedFile::fake()->createWithContent('c1', 'BB')])
            ->assertOk()->assertJsonPath('size', 5);

        $this->postJson(route('challenge-uploads.complete', $id))->assertOk()->assertJsonPath('completed', true);

        $path = app(UploadStore::class)->claim($user, $id);
        $this->assertSame('AAABB', Storage::disk('local')->get($path));
    }

    public function test_out_of_order_chunk_is_rejected(): void
    {
        $user = User::factory()->create();
        $id = $this->actingAs($user)->postJson(route('challenge-uploads.start'))->json('upload_id');

        $this->postJson(route('challenge-uploads.chunk', $id), ['index' => 2, 'chunk' => UploadedFile::fake()->createWithContent('c', 'A')])
            ->assertStatus(422);
    }

    public function test_another_user_cannot_touch_or_claim_the_upload(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $id = $this->actingAs($owner)->postJson(route('challenge-uploads.start'))->json('upload_id');

        $this->actingAs($intruder)
            ->postJson(route('challenge-uploads.chunk', $id), ['index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c', 'A')])
            ->assertStatus(422);

        $this->expectException(ValidationException::class);
        app(UploadStore::class)->claim($intruder, $id);
    }

    public function test_upload_over_the_size_limit_is_rejected(): void
    {
        config(['versus.challenges.max_upload_mb' => 0]);
        $user = User::factory()->create();
        $id = $this->actingAs($user)->postJson(route('challenge-uploads.start'))->json('upload_id');

        $this->postJson(route('challenge-uploads.chunk', $id), ['index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c', 'A')])
            ->assertStatus(422);
    }

    public function test_incomplete_upload_cannot_be_claimed(): void
    {
        $user = User::factory()->create();
        $store = app(UploadStore::class);
        $id = $store->start($user);

        $this->expectException(ValidationException::class);
        $store->claim($user, $id);
    }

    public function test_guest_cannot_start_upload(): void
    {
        $this->postJson(route('challenge-uploads.start'))->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ChallengeUploadTest`
Expected: FAIL with `Route [challenge-uploads.start] not defined`.

- [ ] **Step 3: UploadStore**

`app/Services/Video/UploadStore.php`:

```php
<?php

namespace App\Services\Video;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Chunked uploads on the private `local` disk. Metadata lives in the cache for
 * 24 h; the daily cleanup command removes directories left behind.
 */
class UploadStore
{
    private const TTL_SECONDS = 86400;

    public function start(User $user): string
    {
        $id = (string) Str::uuid();

        Storage::disk('local')->makeDirectory($this->dir($id));
        $this->put($id, ['user_id' => $user->id, 'size' => 0, 'next_chunk' => 0, 'completed' => false]);

        return $id;
    }

    public function appendChunk(User $user, string $uploadId, int $index, UploadedFile $chunk): void
    {
        $meta = $this->ownedMeta($user, $uploadId);

        if ($meta['completed']) {
            throw ValidationException::withMessages(['upload' => __('challenges.invalid_upload')]);
        }

        if ($index < $meta['next_chunk']) {
            return; // Retried chunk that already arrived.
        }

        if ($index !== $meta['next_chunk']) {
            throw ValidationException::withMessages(['chunk' => __('challenges.upload_failed')]);
        }

        $size = $meta['size'] + (int) $chunk->getSize();
        if ($size > $this->maxBytes()) {
            $this->discard($uploadId);

            throw ValidationException::withMessages([
                'upload' => __('challenges.video_too_big', ['mb' => config('versus.challenges.max_upload_mb')]),
            ]);
        }

        $in = fopen((string) $chunk->getRealPath(), 'rb');
        $out = fopen(Storage::disk('local')->path($this->sourcePath($uploadId)), 'ab');
        if ($in === false || $out === false) {
            throw ValidationException::withMessages(['chunk' => __('challenges.upload_failed')]);
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        $meta['size'] = $size;
        $meta['next_chunk'] = $index + 1;
        $this->put($uploadId, $meta);
    }

    /** @return array{next_chunk: int, size: int, completed: bool} */
    public function status(User $user, string $uploadId): array
    {
        $meta = $this->ownedMeta($user, $uploadId);

        return ['next_chunk' => $meta['next_chunk'], 'size' => $meta['size'], 'completed' => $meta['completed']];
    }

    public function complete(User $user, string $uploadId): void
    {
        $meta = $this->ownedMeta($user, $uploadId);

        if ($meta['size'] === 0) {
            throw ValidationException::withMessages(['upload' => __('challenges.invalid_upload')]);
        }

        $meta['completed'] = true;
        $this->put($uploadId, $meta);
    }

    public function claim(User $user, string $uploadId): string
    {
        $meta = $this->ownedMeta($user, $uploadId);

        if (! $meta['completed']) {
            throw ValidationException::withMessages(['upload' => __('challenges.invalid_upload')]);
        }

        Cache::forget($this->key($uploadId));

        return $this->sourcePath($uploadId);
    }

    public function discard(string $uploadId): void
    {
        if (! Str::isUuid($uploadId)) {
            return;
        }

        Cache::forget($this->key($uploadId));
        Storage::disk('local')->deleteDirectory($this->dir($uploadId));
    }

    public function dir(string $uploadId): string
    {
        return 'uploads/'.$uploadId;
    }

    public function chunkBytes(): int
    {
        return (int) config('versus.challenges.upload_chunk_mb') * 1024 * 1024;
    }

    private function sourcePath(string $uploadId): string
    {
        return $this->dir($uploadId).'/source';
    }

    private function maxBytes(): int
    {
        return (int) config('versus.challenges.max_upload_mb') * 1024 * 1024;
    }

    private function key(string $uploadId): string
    {
        return 'challenge-upload:'.$uploadId;
    }

    /** @param array{user_id: int, size: int, next_chunk: int, completed: bool} $meta */
    private function put(string $uploadId, array $meta): void
    {
        Cache::put($this->key($uploadId), $meta, self::TTL_SECONDS);
    }

    /** @return array{user_id: int, size: int, next_chunk: int, completed: bool} */
    private function ownedMeta(User $user, string $uploadId): array
    {
        $meta = Str::isUuid($uploadId) ? Cache::get($this->key($uploadId)) : null;

        if (! is_array($meta) || ($meta['user_id'] ?? null) !== $user->id) {
            throw ValidationException::withMessages(['upload' => __('challenges.invalid_upload')]);
        }

        /** @var array{user_id: int, size: int, next_chunk: int, completed: bool} $meta */
        return $meta;
    }
}
```

- [ ] **Step 4: Controller and routes**

`app/Http/Controllers/ChallengeUploadController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Video\UploadStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ChallengeUploadController extends Controller
{
    public function __construct(private readonly UploadStore $uploads) {}

    public function start(Request $request): JsonResponse
    {
        return response()->json([
            'upload_id' => $this->uploads->start($this->user($request)),
            'chunk_bytes' => $this->uploads->chunkBytes(),
        ]);
    }

    public function chunk(Request $request, string $upload): JsonResponse
    {
        $data = $request->validate([
            'index' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('chunk');
        $this->uploads->appendChunk($this->user($request), $upload, (int) $data['index'], $file);

        return response()->json($this->uploads->status($this->user($request), $upload));
    }

    public function status(Request $request, string $upload): JsonResponse
    {
        return response()->json($this->uploads->status($this->user($request), $upload));
    }

    public function complete(Request $request, string $upload): JsonResponse
    {
        $this->uploads->complete($this->user($request), $upload);

        return response()->json($this->uploads->status($this->user($request), $upload));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
```

In `routes/web.php` add `use App\Http\Controllers\ChallengeUploadController;` to the imports and, inside the existing `Route::middleware(['auth', 'verified'])->group(function () {`, before the closing `});`:

```php
    Route::post('/challenge-uploads', [ChallengeUploadController::class, 'start'])->name('challenge-uploads.start');
    Route::post('/challenge-uploads/{upload}/chunks', [ChallengeUploadController::class, 'chunk'])->name('challenge-uploads.chunk');
    Route::get('/challenge-uploads/{upload}', [ChallengeUploadController::class, 'status'])->name('challenge-uploads.status');
    Route::post('/challenge-uploads/{upload}/complete', [ChallengeUploadController::class, 'complete'])->name('challenge-uploads.complete');
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=ChallengeUploadTest` → PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Video/UploadStore.php app/Http/Controllers/ChallengeUploadController.php routes/web.php tests/Feature/Challenges/ChallengeUploadTest.php
git commit -m "feat(challenges): chunked video upload API

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 4: Video transcoder boundary and ffmpeg in Docker

**Files:**
- Create: `app/Services/Video/VideoTranscoder.php`, `app/Services/Video/VideoProbe.php`, `app/Services/Video/VideoProcessingException.php`, `app/Services/Video/FfmpegVideoTranscoder.php`
- Create: `tests/Fakes/FakeVideoTranscoder.php`
- Modify: `app/Providers/AppServiceProvider.php` (binding in `register()`)
- Modify: `.docker/php/Dockerfile`, `.docker/nginx/default.conf`, `config/queue.php`
- Test: `tests/Feature/Challenges/FfmpegVideoTranscoderTest.php`

**Interfaces:**
- Produces:
  - `interface VideoTranscoder { probe(string $path): VideoProbe; transcode(string $input, string $output): void; poster(string $input, string $output): void; }` — all paths absolute; failures throw `VideoProcessingException`.
  - `final class VideoProbe { __construct(public readonly bool $hasVideo, public readonly int $durationMs) }`
  - `VideoProcessingException::because(string $reasonKey): self` with `public readonly string $reasonKey` — one of `reason_no_video|reason_too_long|reason_too_big|reason_generic` (suffix of `challenges.*`).
  - `Tests\Fakes\FakeVideoTranscoder` with public `bool $hasVideo = true`, `int $durationMs = 15000`, `bool $failTranscode = false`; writes `fake-mp4` / `fake-jpg` to outputs.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Challenges;

use App\Services\Video\FfmpegVideoTranscoder;
use App\Services\Video\VideoProcessingException;
use App\Services\Video\VideoTranscoder;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class FfmpegVideoTranscoderTest extends TestCase
{
    public function test_container_resolves_ffmpeg_transcoder(): void
    {
        $this->assertInstanceOf(FfmpegVideoTranscoder::class, app(VideoTranscoder::class));
    }

    public function test_probe_parses_ffprobe_json(): void
    {
        Process::fake([
            'ffprobe*' => Process::result(json_encode([
                'streams' => [['codec_type' => 'audio'], ['codec_type' => 'video']],
                'format' => ['duration' => '12.345'],
            ])),
        ]);

        $probe = app(FfmpegVideoTranscoder::class)->probe('/tmp/in');

        $this->assertTrue($probe->hasVideo);
        $this->assertSame(12345, $probe->durationMs);
    }

    public function test_probe_failure_is_a_generic_processing_error(): void
    {
        Process::fake(['ffprobe*' => Process::result('', 'broken', 1)]);

        try {
            app(FfmpegVideoTranscoder::class)->probe('/tmp/in');
            $this->fail('Expected exception');
        } catch (VideoProcessingException $e) {
            $this->assertSame('reason_generic', $e->reasonKey);
        }
    }

    public function test_transcode_runs_ffmpeg_with_faststart_720p(): void
    {
        Process::fake(['ffmpeg*' => Process::result()]);

        app(FfmpegVideoTranscoder::class)->transcode('/tmp/in', '/tmp/out.mp4');

        Process::assertRan(fn ($process) => str_contains(implode(' ', (array) $process->command), '+faststart')
            && str_contains(implode(' ', (array) $process->command), 'scale=720:1280'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=FfmpegVideoTranscoderTest`
Expected: FAIL with `Target [App\Services\Video\VideoTranscoder] is not instantiable` / class not found.

- [ ] **Step 3: Implement the boundary**

`app/Services/Video/VideoProbe.php`:

```php
<?php

namespace App\Services\Video;

final class VideoProbe
{
    public function __construct(
        public readonly bool $hasVideo,
        public readonly int $durationMs,
    ) {}
}
```

`app/Services/Video/VideoProcessingException.php`:

```php
<?php

namespace App\Services\Video;

use RuntimeException;

/** A permanent processing failure: retrying the job will not help. */
class VideoProcessingException extends RuntimeException
{
    public function __construct(public readonly string $reasonKey)
    {
        parent::__construct($reasonKey);
    }

    public static function because(string $reasonKey): self
    {
        return new self($reasonKey);
    }
}
```

`app/Services/Video/VideoTranscoder.php`:

```php
<?php

namespace App\Services\Video;

interface VideoTranscoder
{
    /** @throws VideoProcessingException */
    public function probe(string $path): VideoProbe;

    /** @throws VideoProcessingException */
    public function transcode(string $input, string $output): void;

    /** @throws VideoProcessingException */
    public function poster(string $input, string $output): void;
}
```

`app/Services/Video/FfmpegVideoTranscoder.php`:

```php
<?php

namespace App\Services\Video;

use Illuminate\Support\Facades\Process;

class FfmpegVideoTranscoder implements VideoTranscoder
{
    private const FIT_720P = 'scale=720:1280:force_original_aspect_ratio=decrease,pad=720:1280:(ow-iw)/2:(oh-ih)/2:color=black,setsar=1';

    public function probe(string $path): VideoProbe
    {
        $result = Process::timeout(60)->run([
            'ffprobe', '-v', 'error',
            '-show_entries', 'stream=codec_type:format=duration',
            '-of', 'json', $path,
        ]);

        if ($result->failed()) {
            throw VideoProcessingException::because('reason_generic');
        }

        /** @var array{streams?: list<array{codec_type?: string}>, format?: array{duration?: string}}|null $json */
        $json = json_decode($result->output(), true);
        if (! is_array($json)) {
            throw VideoProcessingException::because('reason_generic');
        }

        $hasVideo = collect($json['streams'] ?? [])->contains(fn (array $s): bool => ($s['codec_type'] ?? null) === 'video');
        $durationMs = (int) round(((float) ($json['format']['duration'] ?? 0)) * 1000);

        return new VideoProbe($hasVideo, $durationMs);
    }

    public function transcode(string $input, string $output): void
    {
        $this->run([
            'ffmpeg', '-y', '-i', $input,
            '-vf', self::FIT_720P,
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', '-pix_fmt', 'yuv420p',
            '-c:a', 'aac', '-b:a', '128k',
            '-movflags', '+faststart',
            $output,
        ]);
    }

    public function poster(string $input, string $output): void
    {
        $this->run([
            'ffmpeg', '-y', '-ss', '0.5', '-i', $input,
            '-frames:v', '1', '-vf', self::FIT_720P, '-q:v', '3',
            $output,
        ]);
    }

    /** @param list<string> $command */
    private function run(array $command): void
    {
        $result = Process::timeout(280)->run($command);

        if ($result->failed()) {
            throw VideoProcessingException::because('reason_generic');
        }
    }
}
```

In `app/Providers/AppServiceProvider.php` `register()` add (with `use App\Services\Video\FfmpegVideoTranscoder;` and `use App\Services\Video\VideoTranscoder;`):

```php
        $this->app->bind(VideoTranscoder::class, FfmpegVideoTranscoder::class);
```

`tests/Fakes/FakeVideoTranscoder.php`:

```php
<?php

namespace Tests\Fakes;

use App\Services\Video\VideoProbe;
use App\Services\Video\VideoProcessingException;
use App\Services\Video\VideoTranscoder;

class FakeVideoTranscoder implements VideoTranscoder
{
    public bool $hasVideo = true;

    public int $durationMs = 15000;

    public bool $failTranscode = false;

    public function probe(string $path): VideoProbe
    {
        return new VideoProbe($this->hasVideo, $this->durationMs);
    }

    public function transcode(string $input, string $output): void
    {
        if ($this->failTranscode) {
            throw VideoProcessingException::because('reason_generic');
        }

        file_put_contents($output, 'fake-mp4');
    }

    public function poster(string $input, string $output): void
    {
        file_put_contents($output, 'fake-jpg');
    }
}
```

- [ ] **Step 4: Infrastructure**

In `.docker/php/Dockerfile`, directly after `WORKDIR $APP_HOME` (still root):

```dockerfile
RUN apt-get update && apt-get install -y --no-install-recommends ffmpeg \
    && rm -rf /var/lib/apt/lists/*
```

In `.docker/nginx/default.conf`, inside the `listen 443` server block, before `location / {`, add (nginx handles Range requests for static files natively; this adds long caching for immutable UUID-named media):

```nginx
    location ^~ /storage/videos/ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
        try_files $uri =404;
    }

    location ^~ /storage/posters/ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
        try_files $uri =404;
    }
```

In `config/queue.php`, the `database` connection: change `'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),` to `'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 360),` — the video job's 300 s timeout must stay below `retry_after`, otherwise a second worker picks the same job up mid-transcode.

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=FfmpegVideoTranscoderTest` → PASS.
Then rebuild images and smoke-test on the host: `docker compose build php && docker compose run --rm php ffmpeg -version` → prints an ffmpeg version line.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Video app/Providers/AppServiceProvider.php tests/Fakes/FakeVideoTranscoder.php .docker/php/Dockerfile .docker/nginx/default.conf config/queue.php tests/Feature/Challenges/FfmpegVideoTranscoderTest.php
git commit -m "feat(challenges): ffmpeg transcoder boundary and docker ffmpeg

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 5: Challenge notifications and bell support

**Files:**
- Create: `app/Notifications/ChallengeNotification.php` (abstract base), `ChallengePublished.php`, `EntryProcessingFailed.php`, `ChallengeResponseReceived.php`, `ResponsePublished.php`, `ChallengeEndingSoon.php`, `ChallengeResults.php`
- Modify: `app/Livewire/NotificationBell.php` (`message()`, `url()`)
- Test: `tests/Feature/Challenges/ChallengeNotificationBellTest.php`

**Interfaces:**
- Produces (all database channel, payload always has `challenge_id`, `challenge_slug`, `challenge_title`):
  - `new ChallengePublished(Challenge $challenge)`
  - `new EntryProcessingFailed(Challenge $challenge, string $reasonKey)` → + `reason`
  - `new ChallengeResponseReceived(Challenge $challenge, ChallengeEntry $entry, User $actor)` → + `entry_id`, `actor_name`
  - `new ResponsePublished(Challenge $challenge, ChallengeEntry $entry)` → + `entry_id`
  - `new ChallengeEndingSoon(Challenge $challenge)`
  - `new ChallengeResults(Challenge $challenge, string $result, ?string $winnerName, ?int $entryId)` — `$result` is `ChallengeResults::WON|OVER|NO_VOTES` (`won|over|no_votes`) → + `result`, `winner_name`, `entry_id`
- Bell URLs use plain paths so this task does not depend on page routes: `/c/{slug}` (+ `?entry={id}` when present); `EntryProcessingFailed` → `/my-challenges`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Challenges;

use App\Livewire\NotificationBell;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\User;
use App\Notifications\ChallengeResponseReceived;
use App\Notifications\ChallengeResults;
use App\Notifications\EntryProcessingFailed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChallengeNotificationBellTest extends TestCase
{
    use RefreshDatabase;

    public function test_bell_renders_challenge_notifications_with_links(): void
    {
        $author = User::factory()->create();
        $responder = User::factory()->create(['name' => 'Maya']);
        $challenge = Challenge::factory()->for($author)->create(['title' => 'Kickflip']);
        $entry = ChallengeEntry::factory()->for($challenge)->for($responder)->create();

        $author->notify(new ChallengeResponseReceived($challenge, $entry, $responder));
        $author->notify(new EntryProcessingFailed($challenge, 'reason_too_long'));
        $author->notify(new ChallengeResults($challenge, ChallengeResults::OVER, 'Maya', $entry->id));

        Livewire::actingAs($author)
            ->test(NotificationBell::class)
            ->call('toggle')
            ->assertSee(__('challenges.notif_response_received', ['name' => 'Maya', 'title' => 'Kickflip']))
            ->assertSee(__('challenges.notif_failed', [
                'title' => 'Kickflip',
                'reason' => __('challenges.reason_too_long', ['seconds' => 60]),
            ]))
            ->assertSee(__('challenges.notif_results_over', ['title' => 'Kickflip', 'name' => 'Maya']))
            ->assertSee('/c/'.$challenge->slug.'?entry='.$entry->id, false)
            ->assertSee('/my-challenges', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ChallengeNotificationBellTest`
Expected: FAIL with `Class "App\Notifications\ChallengeResponseReceived" not found`.

- [ ] **Step 3: Notifications**

`app/Notifications/ChallengeNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Challenge;
use Illuminate\Notifications\Notification;

abstract class ChallengeNotification extends Notification
{
    public function __construct(protected readonly Challenge $challenge) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'challenge_id' => $this->challenge->id,
            'challenge_slug' => $this->challenge->slug,
            'challenge_title' => $this->challenge->title,
        ] + $this->extra();
    }

    /** @return array<string, mixed> */
    protected function extra(): array
    {
        return [];
    }
}
```

`app/Notifications/ChallengePublished.php`:

```php
<?php

namespace App\Notifications;

class ChallengePublished extends ChallengeNotification {}
```

`app/Notifications/ChallengeEndingSoon.php`:

```php
<?php

namespace App\Notifications;

class ChallengeEndingSoon extends ChallengeNotification {}
```

`app/Notifications/EntryProcessingFailed.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Challenge;

class EntryProcessingFailed extends ChallengeNotification
{
    public function __construct(Challenge $challenge, private readonly string $reasonKey)
    {
        parent::__construct($challenge);
    }

    protected function extra(): array
    {
        return ['reason' => $this->reasonKey];
    }
}
```

`app/Notifications/ChallengeResponseReceived.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\User;

class ChallengeResponseReceived extends ChallengeNotification
{
    public function __construct(Challenge $challenge, private readonly ChallengeEntry $entry, private readonly User $actor)
    {
        parent::__construct($challenge);
    }

    protected function extra(): array
    {
        return ['entry_id' => $this->entry->id, 'actor_name' => $this->actor->name];
    }
}
```

`app/Notifications/ResponsePublished.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Challenge;
use App\Models\ChallengeEntry;

class ResponsePublished extends ChallengeNotification
{
    public function __construct(Challenge $challenge, private readonly ChallengeEntry $entry)
    {
        parent::__construct($challenge);
    }

    protected function extra(): array
    {
        return ['entry_id' => $this->entry->id];
    }
}
```

`app/Notifications/ChallengeResults.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Challenge;

class ChallengeResults extends ChallengeNotification
{
    public const WON = 'won';

    public const OVER = 'over';

    public const NO_VOTES = 'no_votes';

    public function __construct(
        Challenge $challenge,
        private readonly string $result,
        private readonly ?string $winnerName,
        private readonly ?int $entryId,
    ) {
        parent::__construct($challenge);
    }

    protected function extra(): array
    {
        return ['result' => $this->result, 'winner_name' => $this->winnerName, 'entry_id' => $this->entryId];
    }
}
```

- [ ] **Step 4: Bell**

In `app/Livewire/NotificationBell.php`, inside `message()` add these arms to the `match` before `default`:

```php
            'ChallengePublished' => __('challenges.notif_published', ['title' => (string) $data['challenge_title']]),
            'EntryProcessingFailed' => __('challenges.notif_failed', [
                'title' => (string) $data['challenge_title'],
                'reason' => __('challenges.'.$data['reason'], [
                    'seconds' => config('versus.challenges.max_video_seconds'),
                    'mb' => config('versus.challenges.max_upload_mb'),
                ]),
            ]),
            'ChallengeResponseReceived' => __('challenges.notif_response_received', [
                'name' => (string) $data['actor_name'],
                'title' => (string) $data['challenge_title'],
            ]),
            'ResponsePublished' => __('challenges.notif_response_published', ['title' => (string) $data['challenge_title']]),
            'ChallengeEndingSoon' => __('challenges.notif_ending_soon', ['title' => (string) $data['challenge_title']]),
            'ChallengeResults' => __('challenges.notif_results_'.$data['result'], [
                'title' => (string) $data['challenge_title'],
                'name' => (string) ($data['winner_name'] ?? ''),
            ]),
```

Replace the body of `url()` with:

```php
        /** @var array<string, mixed> $data */
        $data = $notification->data;

        if (isset($data['challenge_slug'])) {
            if (class_basename($notification->type) === 'EntryProcessingFailed') {
                return url('/my-challenges');
            }

            $url = url('/c/'.$data['challenge_slug']);

            return isset($data['entry_id']) ? $url.'?entry='.$data['entry_id'] : $url;
        }

        $url = route('battles.show', ['battle' => (string) $data['battle_slug']]);

        return isset($data['comment_id']) ? $url.'#comment-'.$data['comment_id'] : $url;
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter="ChallengeNotificationBellTest|NotificationPayloadTest"` → PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Notifications app/Livewire/NotificationBell.php tests/Feature/Challenges/ChallengeNotificationBellTest.php
git commit -m "feat(challenges): notifications and bell rendering

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 6: Processing outcome actions and `ProcessEntryVideo` job

**Files:**
- Create: `app/Actions/Challenges/MarkEntryReadyAction.php`, `app/Actions/Challenges/MarkEntryFailedAction.php`
- Create: `app/Jobs/ProcessEntryVideo.php`
- Test: `tests/Feature/Challenges/ProcessEntryVideoTest.php`

**Interfaces:**
- Consumes: `VideoTranscoder`, `VideoProcessingException::$reasonKey`, notifications from Task 5, factories from Task 2.
- Produces:
  - `MarkEntryReadyAction::__invoke(ChallengeEntry $entry, string $videoPath, string $posterPath, int $durationMs): void`
  - `MarkEntryFailedAction::__invoke(ChallengeEntry $entry, string $reasonKey): void` — original: entry `failed` + challenge `failed`; response: entry row deleted.
  - `new ProcessEntryVideo(int $entryId)`, `$tries = 3`, `$timeout = 300`. Expects `entry.source_path` = `entries/{entryId}/source` on the `local` disk; writes `videos/{uuid}.mp4` and `posters/{uuid}.jpg` to the `public` disk; deletes `entries/{entryId}` dir from `local`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Challenges;

use App\Jobs\ProcessEntryVideo;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\User;
use App\Notifications\ChallengePublished;
use App\Notifications\ChallengeResponseReceived;
use App\Notifications\EntryProcessingFailed;
use App\Notifications\ResponsePublished;
use App\Services\Video\VideoTranscoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeVideoTranscoder;
use Tests\TestCase;

class ProcessEntryVideoTest extends TestCase
{
    use RefreshDatabase;

    private FakeVideoTranscoder $transcoder;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Notification::fake();
        $this->transcoder = new FakeVideoTranscoder;
        $this->app->instance(VideoTranscoder::class, $this->transcoder);
    }

    private function pendingEntry(Challenge $challenge, bool $original, ?User $user = null): ChallengeEntry
    {
        $entry = ChallengeEntry::factory()->processing()->create([
            'challenge_id' => $challenge->id,
            'user_id' => $user?->id ?? $challenge->user_id,
            'is_original' => $original,
        ]);
        $entry->update(['source_path' => "entries/{$entry->id}/source"]);
        Storage::disk('local')->put($entry->source_path, 'raw');

        return $entry;
    }

    public function test_ready_original_activates_challenge_and_starts_the_clock(): void
    {
        $this->travelTo(now()->startOfMinute());
        $challenge = Challenge::factory()->processing()->create(['duration' => Challenge::DURATION_3D]);
        $entry = $this->pendingEntry($challenge, true);

        (new ProcessEntryVideo($entry->id))->handle(...$this->handleArgs());

        $entry->refresh();
        $challenge->refresh();
        $this->assertSame(ChallengeEntry::STATUS_READY, $entry->status);
        $this->assertNull($entry->source_path);
        $this->assertSame(15000, $entry->duration_ms);
        Storage::disk('public')->assertExists([$entry->video_path, $entry->poster_path]);
        Storage::disk('local')->assertMissing("entries/{$entry->id}/source");
        $this->assertSame(Challenge::STATUS_ACTIVE, $challenge->status);
        $this->assertTrue($challenge->ends_at->equalTo(now()->addDays(3)));
        Notification::assertSentTo($challenge->user, ChallengePublished::class);
    }

    public function test_ready_response_bumps_count_and_notifies_both_sides(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $responder = User::factory()->create();
        $entry = $this->pendingEntry($challenge, false, $responder);

        (new ProcessEntryVideo($entry->id))->handle(...$this->handleArgs());

        $this->assertSame(1, $challenge->fresh()->entries_count);
        Notification::assertSentTo($challenge->user, ChallengeResponseReceived::class);
        Notification::assertSentTo($responder, ResponsePublished::class);
    }

    public function test_too_long_original_fails_challenge_without_retry(): void
    {
        $this->transcoder->durationMs = 61_000;
        $challenge = Challenge::factory()->processing()->create();
        $entry = $this->pendingEntry($challenge, true);

        (new ProcessEntryVideo($entry->id))->handle(...$this->handleArgs());

        $this->assertSame(ChallengeEntry::STATUS_FAILED, $entry->fresh()->status);
        $this->assertSame('reason_too_long', $entry->fresh()->failure_reason);
        $this->assertSame(Challenge::STATUS_FAILED, $challenge->fresh()->status);
        Notification::assertSentTo($challenge->user, EntryProcessingFailed::class);
    }

    public function test_failed_response_is_deleted_so_the_user_can_retry(): void
    {
        $this->transcoder->hasVideo = false;
        $challenge = Challenge::factory()->withOriginal()->create();
        $responder = User::factory()->create();
        $entry = $this->pendingEntry($challenge, false, $responder);

        (new ProcessEntryVideo($entry->id))->handle(...$this->handleArgs());

        $this->assertNull(ChallengeEntry::find($entry->id));
        Storage::disk('local')->assertMissing("entries/{$entry->id}/source");
        $this->assertSame(Challenge::STATUS_ACTIVE, $challenge->fresh()->status);
        Notification::assertSentTo($responder, EntryProcessingFailed::class);
    }

    public function test_exhausted_retries_mark_generic_failure(): void
    {
        $challenge = Challenge::factory()->processing()->create();
        $entry = $this->pendingEntry($challenge, true);

        (new ProcessEntryVideo($entry->id))->failed(new \RuntimeException('worker died'));

        $this->assertSame('reason_generic', $entry->fresh()->failure_reason);
    }

    public function test_already_processed_entry_is_skipped(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();

        (new ProcessEntryVideo($challenge->original->id))->handle(...$this->handleArgs());

        Notification::assertNothingSent();
    }

    /** @return array{0: VideoTranscoder, 1: \App\Actions\Challenges\MarkEntryReadyAction, 2: \App\Actions\Challenges\MarkEntryFailedAction} */
    private function handleArgs(): array
    {
        return [
            app(VideoTranscoder::class),
            app(\App\Actions\Challenges\MarkEntryReadyAction::class),
            app(\App\Actions\Challenges\MarkEntryFailedAction::class),
        ];
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProcessEntryVideoTest`
Expected: FAIL with `Class "App\Jobs\ProcessEntryVideo" not found`.

- [ ] **Step 3: Actions**

`app/Actions/Challenges/MarkEntryReadyAction.php`:

```php
<?php

namespace App\Actions\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Notifications\ChallengePublished;
use App\Notifications\ChallengeResponseReceived;
use App\Notifications\ResponsePublished;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MarkEntryReadyAction
{
    public function __invoke(ChallengeEntry $entry, string $videoPath, string $posterPath, int $durationMs): void
    {
        $result = DB::transaction(function () use ($entry, $videoPath, $posterPath, $durationMs): ?array {
            /** @var Challenge $challenge */
            $challenge = Challenge::whereKey($entry->challenge_id)->lockForUpdate()->firstOrFail();
            /** @var ChallengeEntry|null $entry */
            $entry = ChallengeEntry::whereKey($entry->id)->lockForUpdate()->first();

            if ($entry === null || $entry->status !== ChallengeEntry::STATUS_PROCESSING) {
                return null;
            }

            $sourcePath = $entry->source_path;
            $entry->fill([
                'status' => ChallengeEntry::STATUS_READY,
                'video_path' => $videoPath,
                'poster_path' => $posterPath,
                'duration_ms' => $durationMs,
                'source_path' => null,
            ])->save();

            if ($entry->is_original) {
                $challenge->status = Challenge::STATUS_ACTIVE;
                $challenge->ends_at = now()->addMinutes(Challenge::durationMinutes($challenge->duration));
                $challenge->save();
            } else {
                $challenge->increment('entries_count');
            }

            return [$challenge, $entry, $sourcePath];
        });

        if ($result === null) {
            return;
        }

        /** @var array{0: Challenge, 1: ChallengeEntry, 2: string|null} $result */
        [$challenge, $entry, $sourcePath] = $result;

        if ($sourcePath !== null) {
            Storage::disk('local')->deleteDirectory(dirname($sourcePath));
        }

        try {
            if ($entry->is_original) {
                $challenge->user->notify(new ChallengePublished($challenge));
            } else {
                $challenge->user->notify(new ChallengeResponseReceived($challenge, $entry, $entry->user));
                $entry->user->notify(new ResponsePublished($challenge, $entry));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
```

`app/Actions/Challenges/MarkEntryFailedAction.php`:

```php
<?php

namespace App\Actions\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\User;
use App\Notifications\EntryProcessingFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MarkEntryFailedAction
{
    public function __invoke(ChallengeEntry $entry, string $reasonKey): void
    {
        $result = DB::transaction(function () use ($entry, $reasonKey): ?array {
            /** @var Challenge $challenge */
            $challenge = Challenge::whereKey($entry->challenge_id)->lockForUpdate()->firstOrFail();
            /** @var ChallengeEntry|null $entry */
            $entry = ChallengeEntry::whereKey($entry->id)->lockForUpdate()->first();

            if ($entry === null || $entry->status !== ChallengeEntry::STATUS_PROCESSING) {
                return null;
            }

            $sourcePath = $entry->source_path;
            /** @var User $uploader */
            $uploader = $entry->user;

            if ($entry->is_original) {
                $entry->fill([
                    'status' => ChallengeEntry::STATUS_FAILED,
                    'failure_reason' => $reasonKey,
                    'source_path' => null,
                ])->save();
                $challenge->status = Challenge::STATUS_FAILED;
                $challenge->save();
            } else {
                // Deleting frees the unique (challenge_id, user_id) slot for a retry.
                $entry->delete();
            }

            return [$challenge, $uploader, $sourcePath];
        });

        if ($result === null) {
            return;
        }

        /** @var array{0: Challenge, 1: User, 2: string|null} $result */
        [$challenge, $uploader, $sourcePath] = $result;

        if ($sourcePath !== null) {
            Storage::disk('local')->deleteDirectory(dirname($sourcePath));
        }

        try {
            $uploader->notify(new EntryProcessingFailed($challenge, $reasonKey));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
```

- [ ] **Step 4: Job**

`app/Jobs/ProcessEntryVideo.php`:

```php
<?php

namespace App\Jobs;

use App\Actions\Challenges\MarkEntryFailedAction;
use App\Actions\Challenges\MarkEntryReadyAction;
use App\Models\ChallengeEntry;
use App\Services\Video\VideoProcessingException;
use App\Services\Video\VideoTranscoder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessEntryVideo implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public readonly int $entryId) {}

    public function handle(VideoTranscoder $transcoder, MarkEntryReadyAction $markReady, MarkEntryFailedAction $markFailed): void
    {
        $entry = ChallengeEntry::find($this->entryId);
        if ($entry === null || $entry->status !== ChallengeEntry::STATUS_PROCESSING || $entry->source_path === null) {
            return;
        }

        $local = Storage::disk('local');
        $public = Storage::disk('public');
        $source = $local->path($entry->source_path);
        $maxSeconds = (int) config('versus.challenges.max_video_seconds');
        $maxBytes = (int) config('versus.challenges.max_upload_mb') * 1024 * 1024;

        try {
            $probe = $transcoder->probe($source);

            if (! $probe->hasVideo) {
                throw VideoProcessingException::because('reason_no_video');
            }
            if ($probe->durationMs > $maxSeconds * 1000 + 500) {
                throw VideoProcessingException::because('reason_too_long');
            }
            if ((int) filesize($source) > $maxBytes) {
                throw VideoProcessingException::because('reason_too_big');
            }

            $uuid = (string) Str::uuid();
            $videoPath = "videos/{$uuid}.mp4";
            $posterPath = "posters/{$uuid}.jpg";
            $public->makeDirectory('videos');
            $public->makeDirectory('posters');

            $transcoder->transcode($source, $public->path($videoPath));
            $transcoder->poster($public->path($videoPath), $public->path($posterPath));
        } catch (VideoProcessingException $e) {
            $markFailed($entry, $e->reasonKey);

            return;
        }

        $markReady($entry, $videoPath, $posterPath, $probe->durationMs);
    }

    public function failed(?Throwable $exception): void
    {
        $entry = ChallengeEntry::find($this->entryId);

        if ($entry !== null) {
            app(MarkEntryFailedAction::class)($entry, 'reason_generic');
        }
    }
}
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=ProcessEntryVideoTest` → PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Challenges/MarkEntryReadyAction.php app/Actions/Challenges/MarkEntryFailedAction.php app/Jobs/ProcessEntryVideo.php tests/Feature/Challenges/ProcessEntryVideoTest.php
git commit -m "feat(challenges): video processing job with ready/failed outcomes

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 7: `CreateChallengeAction`

**Files:**
- Create: `app/Actions/Challenges/CreateChallengeAction.php`
- Test: `tests/Feature/Challenges/CreateChallengeActionTest.php`

**Interfaces:**
- Consumes: `UploadStore::claim()/discard()` (Task 3), `ProcessEntryVideo` (Task 6).
- Produces: `CreateChallengeAction::__invoke(User $user, string $uploadId, string $title, string $rules, string $duration, ?string $username = null, ?Challenge $replacing = null): Challenge` — throws `ValidationException` keyed `title|rules|duration|username|daily_limit|upload|challenge`. Fields are validated **before** the upload is claimed so a typo does not cost a re-upload. `$replacing` must be the user's own `failed` challenge; it is deleted in the same transaction.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Challenges;

use App\Actions\Challenges\CreateChallengeAction;
use App\Jobs\ProcessEntryVideo;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\User;
use App\Services\Video\UploadStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreateChallengeActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
    }

    private function upload(User $user): string
    {
        $store = app(UploadStore::class);
        $id = $store->start($user);
        $store->appendChunk($user, $id, 0, UploadedFile::fake()->createWithContent('c', 'video-bytes'));
        $store->complete($user, $id);

        return $id;
    }

    private function create(User $user, array $overrides = []): Challenge
    {
        $args = array_merge([
            'uploadId' => $this->upload($user),
            'title' => 'Kickflip clean',
            'rules' => 'Land it in 10 seconds #skate',
            'duration' => Challenge::DURATION_3D,
            'username' => null,
            'replacing' => null,
        ], $overrides);

        return app(CreateChallengeAction::class)($user, ...$args);
    }

    public function test_creates_processing_challenge_with_original_and_queues_job(): void
    {
        $user = User::factory()->create(['username' => 'dan']);

        $challenge = $this->create($user);

        $this->assertSame(Challenge::STATUS_PROCESSING, $challenge->status);
        $this->assertNull($challenge->ends_at);
        $this->assertStringStartsWith('kickflip-clean-', $challenge->slug);
        $entry = $challenge->original;
        $this->assertSame(ChallengeEntry::STATUS_PROCESSING, $entry->status);
        $this->assertSame("entries/{$entry->id}/source", $entry->source_path);
        Storage::disk('local')->assertExists($entry->source_path);
        Queue::assertPushed(ProcessEntryVideo::class, fn (ProcessEntryVideo $job) => $job->entryId === $entry->id);
    }

    public function test_invalid_fields_do_not_consume_the_upload(): void
    {
        $user = User::factory()->create(['username' => 'dan']);
        $uploadId = $this->upload($user);

        try {
            $this->create($user, ['uploadId' => $uploadId, 'title' => '   ']);
            $this->fail('Expected validation error');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('title', $e->errors());
        }

        $this->assertSame('video-bytes', Storage::disk('local')->get(app(UploadStore::class)->claim($user, $uploadId)));
    }

    public function test_unknown_duration_is_rejected(): void
    {
        $user = User::factory()->create(['username' => 'dan']);

        $this->expectException(ValidationException::class);
        $this->create($user, ['duration' => '1y']);
    }

    public function test_username_is_required_and_saved_when_missing(): void
    {
        $user = User::factory()->create(['username' => null]);

        try {
            $this->create($user);
            $this->fail('Expected validation error');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('username', $e->errors());
        }

        $this->create($user, ['username' => 'fresh_name']);
        $this->assertSame('fresh_name', $user->fresh()->username);
    }

    public function test_taken_username_is_rejected(): void
    {
        User::factory()->create(['username' => 'taken']);
        $user = User::factory()->create(['username' => null]);

        $this->expectException(ValidationException::class);
        $this->create($user, ['username' => 'taken']);
    }

    public function test_daily_limit_ignores_failed_challenges(): void
    {
        config(['versus.challenges.daily_create_limit' => 2]);
        $user = User::factory()->create(['username' => 'dan']);
        Challenge::factory()->for($user)->count(2)->failed()->create();
        $this->create($user);
        $this->create($user);

        try {
            $this->create($user);
            $this->fail('Expected daily limit');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('daily_limit', $e->errors());
        }
    }

    public function test_replacing_a_failed_challenge_deletes_it(): void
    {
        $user = User::factory()->create(['username' => 'dan']);
        $failed = Challenge::factory()->for($user)->failed()->create();

        $this->create($user, ['replacing' => $failed]);

        $this->assertNull(Challenge::find($failed->id));
    }

    public function test_cannot_replace_someone_elses_or_active_challenge(): void
    {
        $user = User::factory()->create(['username' => 'dan']);
        $active = Challenge::factory()->for($user)->create();

        $this->expectException(ValidationException::class);
        $this->create($user, ['replacing' => $active]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CreateChallengeActionTest`
Expected: FAIL with `Class "App\Actions\Challenges\CreateChallengeAction" not found`.

- [ ] **Step 3: Implement**

`app/Actions/Challenges/CreateChallengeAction.php`:

```php
<?php

namespace App\Actions\Challenges;

use App\Jobs\ProcessEntryVideo;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\User;
use App\Services\Video\UploadStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateChallengeAction
{
    public function __construct(private readonly UploadStore $uploads) {}

    public function __invoke(
        User $user,
        string $uploadId,
        string $title,
        string $rules,
        string $duration,
        ?string $username = null,
        ?Challenge $replacing = null,
    ): Challenge {
        $title = trim($title);
        $rules = trim($rules);
        $username = $username !== null ? trim($username) : null;

        $this->validate($user, $title, $rules, $duration, $username, $replacing);

        $source = $this->uploads->claim($user, $uploadId);

        $challenge = DB::transaction(function () use ($user, $title, $rules, $duration, $username, $replacing, $source): Challenge {
            if ($user->username === null && $username !== null) {
                $user->forceFill(['username' => $username])->save();
            }

            if ($replacing !== null) {
                Challenge::whereKey($replacing->id)->lockForUpdate()->firstOrFail()->delete();
            }

            $challenge = Challenge::create([
                'slug' => (Str::slug($title) ?: 'challenge').'-'.Str::lower(Str::random(6)),
                'user_id' => $user->id,
                'title' => $title,
                'rules' => $rules,
                'duration' => $duration,
                'status' => Challenge::STATUS_PROCESSING,
            ]);

            $entry = ChallengeEntry::create([
                'challenge_id' => $challenge->id,
                'user_id' => $user->id,
                'is_original' => true,
                'status' => ChallengeEntry::STATUS_PROCESSING,
                'submitted_at' => now(),
            ]);

            $destination = "entries/{$entry->id}/source";
            Storage::disk('local')->move($source, $destination);
            $entry->update(['source_path' => $destination]);

            ProcessEntryVideo::dispatch($entry->id)->afterCommit();

            return $challenge;
        });

        $this->uploads->discard($uploadId);

        return $challenge;
    }

    private function validate(User $user, string $title, string $rules, string $duration, ?string $username, ?Challenge $replacing): void
    {
        $errors = [];

        if ($title === '' || mb_strlen($title) > 100) {
            $errors['title'] = __('challenges.title_required');
        }
        if ($rules === '' || mb_strlen($rules) > 500) {
            $errors['rules'] = __('challenges.rules_required');
        }
        if (! array_key_exists($duration, (array) config('versus.challenges.durations'))) {
            $errors['duration'] = __('challenges.duration_required');
        }

        if ($user->username === null) {
            if ($username === null || preg_match('/^[a-zA-Z0-9_]{3,32}$/', $username) !== 1) {
                $errors['username'] = __('challenges.username_required');
            } elseif (User::where('username', $username)->exists()) {
                $errors['username'] = __('challenges.username_taken');
            }
        }

        if ($replacing !== null && ($replacing->user_id !== $user->id || $replacing->status !== Challenge::STATUS_FAILED)) {
            $errors['challenge'] = __('challenges.cannot_retry');
        }

        $limit = (int) config('versus.challenges.daily_create_limit');
        $createdToday = Challenge::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', Challenge::STATUS_FAILED)
            ->where('created_at', '>=', now()->subDay())
            ->count();
        if ($createdToday >= $limit) {
            $errors['daily_limit'] = __('challenges.daily_limit', ['limit' => $limit]);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=CreateChallengeActionTest` → PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Actions/Challenges/CreateChallengeAction.php tests/Feature/Challenges/CreateChallengeActionTest.php
git commit -m "feat(challenges): create challenge action

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 8: Respond, accept and leave actions

**Files:**
- Create: `app/Actions/Challenges/SubmitResponseAction.php`, `app/Actions/Challenges/AcceptChallengeAction.php`, `app/Actions/Challenges/LeaveChallengeAction.php`
- Test: `tests/Feature/Challenges/ParticipationActionsTest.php`

**Interfaces:**
- Consumes: `UploadStore`, `ProcessEntryVideo`, `Challenge::isOpen()`.
- Produces:
  - `SubmitResponseAction::__invoke(User $user, Challenge $challenge, string $uploadId): ChallengeEntry` — errors keyed `challenge` (`not_open|own_challenge|already_responded`) or `upload`; any failure after claiming discards the upload.
  - `AcceptChallengeAction::__invoke(User $user, Challenge $challenge): ChallengeParticipant` — idempotent; errors `challenge` (`own_challenge|not_open`).
  - `LeaveChallengeAction::__invoke(User $user, Challenge $challenge): void` — error `challenge` (`cannot_leave_with_entry`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Challenges;

use App\Actions\Challenges\AcceptChallengeAction;
use App\Actions\Challenges\LeaveChallengeAction;
use App\Actions\Challenges\SubmitResponseAction;
use App\Jobs\ProcessEntryVideo;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeParticipant;
use App\Models\User;
use App\Services\Video\UploadStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ParticipationActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
    }

    private function upload(User $user): string
    {
        $store = app(UploadStore::class);
        $id = $store->start($user);
        $store->appendChunk($user, $id, 0, UploadedFile::fake()->createWithContent('c', 'bytes'));
        $store->complete($user, $id);

        return $id;
    }

    private function assertChallengeError(callable $fn, string $message): void
    {
        try {
            $fn();
            $this->fail('Expected validation error');
        } catch (ValidationException $e) {
            $this->assertSame($message, $e->errors()['challenge'][0] ?? null);
        }
    }

    public function test_response_creates_processing_entry_participant_and_job(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $user = User::factory()->create();

        $entry = app(SubmitResponseAction::class)($user, $challenge, $this->upload($user));

        $this->assertFalse($entry->is_original);
        $this->assertSame(ChallengeEntry::STATUS_PROCESSING, $entry->status);
        Storage::disk('local')->assertExists("entries/{$entry->id}/source");
        $this->assertTrue(ChallengeParticipant::where(['user_id' => $user->id, 'challenge_id' => $challenge->id])->exists());
        Queue::assertPushed(ProcessEntryVideo::class);
    }

    public function test_response_after_deadline_is_rejected_and_upload_deleted(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create(['ends_at' => now()->subSecond()]);
        $user = User::factory()->create();
        $uploadId = $this->upload($user);

        $this->assertChallengeError(
            fn () => app(SubmitResponseAction::class)($user, $challenge, $uploadId),
            __('challenges.not_open'),
        );
        Storage::disk('local')->assertMissing(app(UploadStore::class)->dir($uploadId));
    }

    public function test_author_cannot_respond_to_own_challenge(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();

        $this->assertChallengeError(
            fn () => app(SubmitResponseAction::class)($challenge->user, $challenge, $this->upload($challenge->user)),
            __('challenges.own_challenge'),
        );
    }

    public function test_second_response_is_rejected(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $user = User::factory()->create();
        ChallengeEntry::factory()->for($challenge)->for($user)->create();

        $this->assertChallengeError(
            fn () => app(SubmitResponseAction::class)($user, $challenge, $this->upload($user)),
            __('challenges.already_responded'),
        );
    }

    public function test_accept_is_idempotent_and_blocked_for_author_and_closed(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $user = User::factory()->create();

        app(AcceptChallengeAction::class)($user, $challenge);
        app(AcceptChallengeAction::class)($user, $challenge);
        $this->assertSame(1, ChallengeParticipant::count());

        $this->assertChallengeError(fn () => app(AcceptChallengeAction::class)($challenge->user, $challenge), __('challenges.own_challenge'));
        $closed = Challenge::factory()->closed()->create();
        $this->assertChallengeError(fn () => app(AcceptChallengeAction::class)($user, $closed), __('challenges.not_open'));
    }

    public function test_leave_removes_participation_unless_user_responded(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $user = User::factory()->create();
        app(AcceptChallengeAction::class)($user, $challenge);

        app(LeaveChallengeAction::class)($user, $challenge);
        $this->assertSame(0, ChallengeParticipant::count());

        app(AcceptChallengeAction::class)($user, $challenge);
        ChallengeEntry::factory()->for($challenge)->for($user)->create();
        $this->assertChallengeError(fn () => app(LeaveChallengeAction::class)($user, $challenge), __('challenges.cannot_leave_with_entry'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ParticipationActionsTest`
Expected: FAIL with `Class "App\Actions\Challenges\SubmitResponseAction" not found`.

- [ ] **Step 3: Implement**

`app/Actions/Challenges/SubmitResponseAction.php`:

```php
<?php

namespace App\Actions\Challenges;

use App\Jobs\ProcessEntryVideo;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeParticipant;
use App\Models\User;
use App\Services\Video\UploadStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SubmitResponseAction
{
    public function __construct(private readonly UploadStore $uploads) {}

    public function __invoke(User $user, Challenge $challenge, string $uploadId): ChallengeEntry
    {
        $source = $this->uploads->claim($user, $uploadId);

        try {
            $entry = DB::transaction(function () use ($user, $challenge, $source): ChallengeEntry {
                /** @var Challenge $challenge */
                $challenge = Challenge::whereKey($challenge->id)->lockForUpdate()->firstOrFail();

                if (! $challenge->isOpen()) {
                    throw ValidationException::withMessages(['challenge' => __('challenges.not_open')]);
                }
                if ($challenge->user_id === $user->id) {
                    throw ValidationException::withMessages(['challenge' => __('challenges.own_challenge')]);
                }
                if (ChallengeEntry::where('challenge_id', $challenge->id)->where('user_id', $user->id)->exists()) {
                    throw ValidationException::withMessages(['challenge' => __('challenges.already_responded')]);
                }

                $entry = ChallengeEntry::create([
                    'challenge_id' => $challenge->id,
                    'user_id' => $user->id,
                    'is_original' => false,
                    'status' => ChallengeEntry::STATUS_PROCESSING,
                    'submitted_at' => now(),
                ]);

                $destination = "entries/{$entry->id}/source";
                Storage::disk('local')->move($source, $destination);
                $entry->update(['source_path' => $destination]);

                ChallengeParticipant::firstOrCreate(
                    ['user_id' => $user->id, 'challenge_id' => $challenge->id],
                    ['accepted_at' => now()],
                );

                ProcessEntryVideo::dispatch($entry->id)->afterCommit();

                return $entry;
            });
        } finally {
            $this->uploads->discard($uploadId);
        }

        return $entry;
    }
}
```

`app/Actions/Challenges/AcceptChallengeAction.php`:

```php
<?php

namespace App\Actions\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeParticipant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AcceptChallengeAction
{
    public function __invoke(User $user, Challenge $challenge): ChallengeParticipant
    {
        if ($challenge->user_id === $user->id) {
            throw ValidationException::withMessages(['challenge' => __('challenges.own_challenge')]);
        }
        if (! $challenge->isOpen()) {
            throw ValidationException::withMessages(['challenge' => __('challenges.not_open')]);
        }

        return ChallengeParticipant::firstOrCreate(
            ['user_id' => $user->id, 'challenge_id' => $challenge->id],
            ['accepted_at' => now()],
        );
    }
}
```

`app/Actions/Challenges/LeaveChallengeAction.php`:

```php
<?php

namespace App\Actions\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeParticipant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class LeaveChallengeAction
{
    public function __invoke(User $user, Challenge $challenge): void
    {
        if (ChallengeEntry::where('challenge_id', $challenge->id)->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages(['challenge' => __('challenges.cannot_leave_with_entry')]);
        }

        ChallengeParticipant::where('challenge_id', $challenge->id)->where('user_id', $user->id)->delete();
    }
}
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=ParticipationActionsTest` → PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Actions/Challenges/SubmitResponseAction.php app/Actions/Challenges/AcceptChallengeAction.php app/Actions/Challenges/LeaveChallengeAction.php tests/Feature/Challenges/ParticipationActionsTest.php
git commit -m "feat(challenges): respond, accept and leave actions

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 9: `CastChallengeVoteAction`

**Files:**
- Create: `app/Actions/Challenges/CastChallengeVoteAction.php`
- Test: `tests/Feature/Challenges/CastChallengeVoteActionTest.php`

**Interfaces:**
- Produces: `CastChallengeVoteAction::__invoke(User $user, ChallengeEntry $entry): ChallengeVote` — errors keyed `challenge` (`not_open`) or `entry` (`entry_not_votable|cannot_vote_own`). Locks the challenge row first (same lock as `CloseChallengeAction`), then entries.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Challenges;

use App\Actions\Challenges\CastChallengeVoteAction;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeVote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CastChallengeVoteActionTest extends TestCase
{
    use RefreshDatabase;

    private function vote(User $user, ChallengeEntry $entry): ChallengeVote
    {
        return app(CastChallengeVoteAction::class)($user, $entry);
    }

    public function test_first_vote_increments_entry_and_challenge(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $entry = ChallengeEntry::factory()->for($challenge)->create();
        $voter = User::factory()->create();

        $this->vote($voter, $entry);

        $this->assertSame(1, $entry->fresh()->votes_count);
        $this->assertSame(1, $challenge->fresh()->votes_count);
    }

    public function test_voting_for_the_original_is_allowed(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();

        $this->vote(User::factory()->create(), $challenge->original);

        $this->assertSame(1, $challenge->original->fresh()->votes_count);
    }

    public function test_moving_a_vote_shifts_counters_and_keeps_total(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $a = ChallengeEntry::factory()->for($challenge)->create();
        $b = ChallengeEntry::factory()->for($challenge)->create();
        $voter = User::factory()->create();

        $this->vote($voter, $a);
        $this->vote($voter, $b);

        $this->assertSame(0, $a->fresh()->votes_count);
        $this->assertSame(1, $b->fresh()->votes_count);
        $this->assertSame(1, $challenge->fresh()->votes_count);
        $this->assertSame(1, ChallengeVote::count());
    }

    public function test_repeat_vote_for_same_entry_is_a_noop(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $a = ChallengeEntry::factory()->for($challenge)->create();
        $voter = User::factory()->create();

        $this->vote($voter, $a);
        $this->vote($voter, $a);

        $this->assertSame(1, $a->fresh()->votes_count);
    }

    public function test_cannot_vote_for_own_entry(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();

        $this->expectException(ValidationException::class);
        $this->vote($challenge->user, $challenge->original);
    }

    public function test_cannot_vote_for_processing_entry(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $entry = ChallengeEntry::factory()->for($challenge)->processing()->create();

        $this->expectException(ValidationException::class);
        $this->vote(User::factory()->create(), $entry);
    }

    public function test_cannot_vote_after_deadline_even_before_close_command_runs(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create(['ends_at' => now()->subSecond()]);

        $this->expectException(ValidationException::class);
        $this->vote(User::factory()->create(), $challenge->original);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CastChallengeVoteActionTest`
Expected: FAIL with `Class "App\Actions\Challenges\CastChallengeVoteAction" not found`.

- [ ] **Step 3: Implement**

`app/Actions/Challenges/CastChallengeVoteAction.php`:

```php
<?php

namespace App\Actions\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeVote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CastChallengeVoteAction
{
    public function __invoke(User $user, ChallengeEntry $entry): ChallengeVote
    {
        return DB::transaction(function () use ($user, $entry): ChallengeVote {
            /** @var Challenge $challenge */
            $challenge = Challenge::whereKey($entry->challenge_id)->lockForUpdate()->firstOrFail();

            if (! $challenge->isOpen()) {
                throw ValidationException::withMessages(['challenge' => __('challenges.not_open')]);
            }

            /** @var ChallengeEntry $entry */
            $entry = ChallengeEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if ($entry->status !== ChallengeEntry::STATUS_READY) {
                throw ValidationException::withMessages(['entry' => __('challenges.entry_not_votable')]);
            }
            if ($entry->user_id === $user->id) {
                throw ValidationException::withMessages(['entry' => __('challenges.cannot_vote_own')]);
            }

            /** @var ChallengeVote|null $vote */
            $vote = ChallengeVote::query()
                ->where('user_id', $user->id)
                ->where('challenge_id', $challenge->id)
                ->lockForUpdate()
                ->first();

            if ($vote === null) {
                $vote = ChallengeVote::create([
                    'user_id' => $user->id,
                    'challenge_id' => $challenge->id,
                    'entry_id' => $entry->id,
                ]);
                $entry->increment('votes_count');
                $challenge->increment('votes_count');

                return $vote;
            }

            if ($vote->entry_id === $entry->id) {
                return $vote;
            }

            ChallengeEntry::whereKey($vote->entry_id)
                ->where('votes_count', '>', 0)
                ->lockForUpdate()
                ->decrement('votes_count');

            $vote->entry_id = $entry->id;
            $vote->save();
            $entry->increment('votes_count');

            return $vote;
        });
    }
}
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=CastChallengeVoteActionTest` → PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Actions/Challenges/CastChallengeVoteAction.php tests/Feature/Challenges/CastChallengeVoteActionTest.php
git commit -m "feat(challenges): one movable vote per challenge

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 10: Closing, results, reminders and the scheduler

**Files:**
- Create: `app/Events/ChallengeClosed.php`
- Create: `app/Actions/Challenges/CloseChallengeAction.php`
- Create: `app/Console/Commands/CloseDueChallengesCommand.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Challenges/CloseChallengeTest.php`

**Interfaces:**
- Produces:
  - `ChallengeClosed` event: `public readonly Challenge $challenge`, `use Dispatchable`.
  - `CloseChallengeAction::__invoke(Challenge $challenge): Challenge` — idempotent; no-op unless `active` and `ends_at <= now()`.
  - Artisan `challenges:close-due` — closes due challenges and sends `ChallengeEndingSoon` once per challenge to participants without an entry when `ends_at` is within `reminder_minutes_before`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Challenges;

use App\Actions\Challenges\CastChallengeVoteAction;
use App\Actions\Challenges\CloseChallengeAction;
use App\Events\ChallengeClosed;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeParticipant;
use App\Models\User;
use App\Notifications\ChallengeEndingSoon;
use App\Notifications\ChallengeResults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CloseChallengeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function votes(ChallengeEntry $entry, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            app(CastChallengeVoteAction::class)(User::factory()->create(), $entry);
        }
    }

    private function expire(Challenge $challenge): Challenge
    {
        $challenge->update(['ends_at' => now()->subSecond()]);

        return $challenge->fresh();
    }

    public function test_most_voted_entry_wins_and_everyone_with_an_entry_is_notified(): void
    {
        Event::fake([ChallengeClosed::class]);
        $challenge = Challenge::factory()->withOriginal()->create();
        $a = ChallengeEntry::factory()->for($challenge)->create();
        $b = ChallengeEntry::factory()->for($challenge)->create();
        $this->votes($a, 1);
        $this->votes($b, 2);

        $closed = app(CloseChallengeAction::class)($this->expire($challenge));

        $this->assertSame(Challenge::STATUS_CLOSED, $closed->status);
        $this->assertSame($b->id, $closed->winner_entry_id);
        $this->assertSame(3, $closed->votes_count);
        $this->assertNotNull($closed->closed_at);
        Event::assertDispatched(ChallengeClosed::class);
        Notification::assertSentTo($b->user, ChallengeResults::class, fn ($n) => $n->toDatabase($b->user)['result'] === ChallengeResults::WON);
        Notification::assertSentTo($a->user, ChallengeResults::class, fn ($n) => $n->toDatabase($a->user)['result'] === ChallengeResults::OVER);
        Notification::assertSentTo($challenge->user, ChallengeResults::class);
    }

    public function test_tie_goes_to_the_earlier_submission_so_the_original_wins_ties(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $challenge->original->update(['submitted_at' => now()->subHour()]);
        $response = ChallengeEntry::factory()->for($challenge)->create(['submitted_at' => now()]);
        $this->votes($challenge->original, 1);
        $this->votes($response, 1);

        $closed = app(CloseChallengeAction::class)($this->expire($challenge));

        $this->assertSame($challenge->original->id, $closed->winner_entry_id);
    }

    public function test_zero_votes_means_no_winner(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        ChallengeEntry::factory()->for($challenge)->create();

        $closed = app(CloseChallengeAction::class)($this->expire($challenge));

        $this->assertNull($closed->winner_entry_id);
        Notification::assertSentTo($challenge->user, ChallengeResults::class, fn ($n) => $n->toDatabase($challenge->user)['result'] === ChallengeResults::NO_VOTES);
    }

    public function test_recount_ignores_stale_cached_counters(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $a = ChallengeEntry::factory()->for($challenge)->create(['votes_count' => 50]);
        $b = ChallengeEntry::factory()->for($challenge)->create();
        $this->votes($b, 1);

        $closed = app(CloseChallengeAction::class)($this->expire($challenge));

        $this->assertSame($b->id, $closed->winner_entry_id);
        $this->assertSame(0, $a->fresh()->votes_count);
    }

    public function test_not_yet_due_and_already_closed_are_noops(): void
    {
        Event::fake([ChallengeClosed::class]);
        $open = Challenge::factory()->withOriginal()->create();
        app(CloseChallengeAction::class)($open);
        $this->assertSame(Challenge::STATUS_ACTIVE, $open->fresh()->status);

        $closed = app(CloseChallengeAction::class)($this->expire($open));
        app(CloseChallengeAction::class)($closed);
        Event::assertDispatchedTimes(ChallengeClosed::class, 1);
    }

    public function test_command_closes_due_and_reminds_once(): void
    {
        $due = Challenge::factory()->withOriginal()->create(['ends_at' => now()->subMinute()]);
        $soon = Challenge::factory()->withOriginal()->create(['ends_at' => now()->addMinutes(30)]);
        $waiting = User::factory()->create();
        $answered = User::factory()->create();
        ChallengeParticipant::create(['user_id' => $waiting->id, 'challenge_id' => $soon->id, 'accepted_at' => now()]);
        ChallengeParticipant::create(['user_id' => $answered->id, 'challenge_id' => $soon->id, 'accepted_at' => now()]);
        ChallengeEntry::factory()->for($soon)->for($answered)->create();

        $this->artisan('challenges:close-due')->assertSuccessful();
        $this->artisan('challenges:close-due')->assertSuccessful();

        $this->assertSame(Challenge::STATUS_CLOSED, $due->fresh()->status);
        $this->assertSame(Challenge::STATUS_ACTIVE, $soon->fresh()->status);
        Notification::assertSentToTimes($waiting, ChallengeEndingSoon::class, 1);
        Notification::assertNotSentTo($answered, ChallengeEndingSoon::class);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CloseChallengeTest`
Expected: FAIL with `Class "App\Events\ChallengeClosed" not found`.

- [ ] **Step 3: Event and action**

`app/Events/ChallengeClosed.php`:

```php
<?php

namespace App\Events;

use App\Models\Challenge;
use Illuminate\Foundation\Events\Dispatchable;

class ChallengeClosed
{
    use Dispatchable;

    public function __construct(public readonly Challenge $challenge) {}
}
```

`app/Actions/Challenges/CloseChallengeAction.php`:

```php
<?php

namespace App\Actions\Challenges;

use App\Events\ChallengeClosed;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeVote;
use App\Notifications\ChallengeResults;
use Illuminate\Support\Facades\DB;
use Throwable;

class CloseChallengeAction
{
    public function __invoke(Challenge $challenge): Challenge
    {
        $closed = DB::transaction(function () use ($challenge): ?Challenge {
            /** @var Challenge $challenge */
            $challenge = Challenge::whereKey($challenge->id)->lockForUpdate()->firstOrFail();

            if ($challenge->status !== Challenge::STATUS_ACTIVE || $challenge->ends_at === null || now()->lt($challenge->ends_at)) {
                return null;
            }

            /** @var array<int, int> $counts */
            $counts = ChallengeVote::query()
                ->where('challenge_id', $challenge->id)
                ->selectRaw('entry_id, COUNT(*) as aggregate')
                ->groupBy('entry_id')
                ->pluck('aggregate', 'entry_id')
                ->map(fn ($v): int => (int) $v)
                ->all();

            $entries = ChallengeEntry::query()
                ->where('challenge_id', $challenge->id)
                ->where('status', ChallengeEntry::STATUS_READY)
                ->orderBy('submitted_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $winner = null;
            $best = 0;
            foreach ($entries as $entry) {
                $votes = $counts[$entry->id] ?? 0;
                if ($entry->votes_count !== $votes) {
                    $entry->votes_count = $votes;
                    $entry->save();
                }
                // Strictly greater: on a tie the earlier submission keeps the lead.
                if ($votes > $best) {
                    $best = $votes;
                    $winner = $entry;
                }
            }

            $challenge->fill([
                'status' => Challenge::STATUS_CLOSED,
                'winner_entry_id' => $winner?->id,
                'closed_at' => now(),
                'votes_count' => array_sum($counts),
                'feed_score' => 0,
            ])->save();

            return $challenge;
        });

        if ($closed === null) {
            return $challenge->fresh() ?? $challenge;
        }

        ChallengeClosed::dispatch($closed);
        $this->notifyResults($closed);

        return $closed;
    }

    private function notifyResults(Challenge $challenge): void
    {
        $winner = $challenge->winnerEntry()->with('user')->first();

        $entries = ChallengeEntry::query()
            ->where('challenge_id', $challenge->id)
            ->where('status', ChallengeEntry::STATUS_READY)
            ->with('user')
            ->get()
            ->unique('user_id');

        foreach ($entries as $entry) {
            $result = match (true) {
                $winner === null => ChallengeResults::NO_VOTES,
                $winner->id === $entry->id => ChallengeResults::WON,
                default => ChallengeResults::OVER,
            };

            try {
                $entry->user->notify(new ChallengeResults($challenge, $result, $winner?->user?->name, $winner?->id));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
```

- [ ] **Step 4: Command and schedule**

`app/Console/Commands/CloseDueChallengesCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Actions\Challenges\CloseChallengeAction;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeParticipant;
use App\Notifications\ChallengeEndingSoon;
use Illuminate\Console\Command;
use Throwable;

class CloseDueChallengesCommand extends Command
{
    protected $signature = 'challenges:close-due';

    protected $description = 'Close challenges past their deadline and send "1 hour left" reminders.';

    public function handle(CloseChallengeAction $close): int
    {
        Challenge::query()
            ->where('status', Challenge::STATUS_ACTIVE)
            ->where('ends_at', '<=', now())
            ->each(function (Challenge $challenge) use ($close): void {
                try {
                    $close($challenge);
                    $this->info("Closed challenge #{$challenge->id}.");
                } catch (Throwable $e) {
                    report($e);
                    $this->error("Failed to close challenge #{$challenge->id}: {$e->getMessage()}");
                }
            });

        $this->sendReminders();

        return self::SUCCESS;
    }

    private function sendReminders(): void
    {
        $horizon = now()->addMinutes((int) config('versus.challenges.reminder_minutes_before'));

        Challenge::query()
            ->where('status', Challenge::STATUS_ACTIVE)
            ->whereNull('reminder_sent_at')
            ->where('ends_at', '>', now())
            ->where('ends_at', '<=', $horizon)
            ->each(function (Challenge $challenge): void {
                // Conditional update claims the reminder so overlapping runs cannot double-send.
                $claimed = Challenge::whereKey($challenge->id)->whereNull('reminder_sent_at')->update(['reminder_sent_at' => now()]);
                if ($claimed === 0) {
                    return;
                }

                $answered = ChallengeEntry::where('challenge_id', $challenge->id)->pluck('user_id');

                ChallengeParticipant::query()
                    ->where('challenge_id', $challenge->id)
                    ->whereNotIn('user_id', $answered)
                    ->with('user')
                    ->each(function (ChallengeParticipant $participant) use ($challenge): void {
                        try {
                            $participant->user->notify(new ChallengeEndingSoon($challenge));
                        } catch (Throwable $e) {
                            report($e);
                        }
                    });
            });
    }
}
```

In `routes/console.php` append:

```php
Schedule::command('challenges:close-due')
    ->everyMinute()
    ->withoutOverlapping();
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=CloseChallengeTest` → PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Events/ChallengeClosed.php app/Actions/Challenges/CloseChallengeAction.php app/Console/Commands/CloseDueChallengesCommand.php routes/console.php tests/Feature/Challenges/CloseChallengeTest.php
git commit -m "feat(challenges): close due challenges, pick winner, remind participants

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 11: Likes, impressions and entry comments

**Files:**
- Create: `database/migrations/2026_09_15_100100_add_challenge_entry_id_to_comments_table.php`
- Modify: `app/Models/Comment.php` (fillable + `entry()` relation)
- Create: `app/Actions/Challenges/ToggleEntryLikeAction.php`, `app/Actions/Challenges/RecordImpressionsAction.php`, `app/Actions/Challenges/PostEntryCommentAction.php`
- Modify: `app/Livewire/ProfilePage.php:224` and `app/Services/Feed/FeedService.php:149` — scope to battle comments
- Modify: `docs/superpowers/specs/2026-09-15-challenges-core-design.md` (comments paragraph)
- Test: `tests/Feature/Challenges/EntryInteractionsTest.php`

**Interfaces:**
- Produces:
  - `ToggleEntryLikeAction::__invoke(User $user, ChallengeEntry $entry): array{liked: bool, likes_count: int}` — error `entry` (`entry_not_votable`) if not ready.
  - `RecordImpressionsAction::__invoke(array $entryIds): void` — accepts ints/numeric strings, dedupes, caps at 20, increments ready entries only.
  - `PostEntryCommentAction::__invoke(User $user, ChallengeEntry $entry, string $body): Comment` — error `body` (`comment_required`) when empty or longer than `comment_max_length`; `entry` when not ready.
  - `Comment::entry(): BelongsTo<ChallengeEntry>`; `comments.battle_id` nullable.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Challenges;

use App\Actions\Challenges\PostEntryCommentAction;
use App\Actions\Challenges\RecordImpressionsAction;
use App\Actions\Challenges\ToggleEntryLikeAction;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EntryInteractionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_like_toggles_on_and_off(): void
    {
        $entry = ChallengeEntry::factory()->create();
        $user = User::factory()->create();

        $this->assertSame(['liked' => true, 'likes_count' => 1], app(ToggleEntryLikeAction::class)($user, $entry));
        $this->assertSame(['liked' => false, 'likes_count' => 0], app(ToggleEntryLikeAction::class)($user, $entry));
    }

    public function test_cannot_like_processing_entry(): void
    {
        $entry = ChallengeEntry::factory()->processing()->create();

        $this->expectException(ValidationException::class);
        app(ToggleEntryLikeAction::class)(User::factory()->create(), $entry);
    }

    public function test_impressions_are_deduped_capped_and_ready_only(): void
    {
        $ready = ChallengeEntry::factory()->create();
        $processing = ChallengeEntry::factory()->processing()->create();

        app(RecordImpressionsAction::class)([$ready->id, (string) $ready->id, $processing->id, 'junk']);

        $this->assertSame(1, $ready->fresh()->impressions_count);
        $this->assertSame(0, $processing->fresh()->impressions_count);
    }

    public function test_comment_is_stored_against_the_entry_without_a_battle(): void
    {
        $entry = ChallengeEntry::factory()->create();
        $user = User::factory()->create();

        $comment = app(PostEntryCommentAction::class)($user, $entry, '  nice kickflip ');

        $this->assertSame('nice kickflip', $comment->body);
        $this->assertNull($comment->battle_id);
        $this->assertSame($entry->id, $comment->challenge_entry_id);
    }

    public function test_blank_comment_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(PostEntryCommentAction::class)(User::factory()->create(), ChallengeEntry::factory()->create(), '   ');
    }

    public function test_profile_page_ignores_entry_comments(): void
    {
        $user = User::factory()->create();
        app(PostEntryCommentAction::class)($user, ChallengeEntry::factory()->create(), 'video comment');

        $this->actingAs($user)->get(route('profile.edit', ['tab' => 'comments']))->assertOk();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=EntryInteractionsTest`
Expected: FAIL with `Class "App\Actions\Challenges\ToggleEntryLikeAction" not found`.

- [ ] **Step 3: Migration and model**

`database/migrations/2026_09_15_100100_add_challenge_entry_id_to_comments_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->unsignedBigInteger('battle_id')->nullable()->change();
            $table->foreignId('challenge_entry_id')->nullable()->after('battle_id')
                ->constrained('challenge_entries')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('challenge_entry_id');
        });
    }
};
```

In `app/Models/Comment.php`: add `'challenge_entry_id'` to `#[Fillable([...])]` and the relation:

```php
    /**
     * @return BelongsTo<ChallengeEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(ChallengeEntry::class, 'challenge_entry_id');
    }
```

- [ ] **Step 4: Actions**

`app/Actions/Challenges/ToggleEntryLikeAction.php`:

```php
<?php

namespace App\Actions\Challenges;

use App\Models\ChallengeEntry;
use App\Models\EntryLike;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ToggleEntryLikeAction
{
    /** @return array{liked: bool, likes_count: int} */
    public function __invoke(User $user, ChallengeEntry $entry): array
    {
        return DB::transaction(function () use ($user, $entry): array {
            /** @var ChallengeEntry $entry */
            $entry = ChallengeEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if ($entry->status !== ChallengeEntry::STATUS_READY) {
                throw ValidationException::withMessages(['entry' => __('challenges.entry_not_votable')]);
            }

            $existing = EntryLike::where('user_id', $user->id)->where('entry_id', $entry->id)->first();

            if ($existing !== null) {
                $existing->delete();
                $entry->likes_count = max(0, $entry->likes_count - 1);
            } else {
                EntryLike::create(['user_id' => $user->id, 'entry_id' => $entry->id]);
                $entry->likes_count++;
            }
            $entry->save();

            return ['liked' => $existing === null, 'likes_count' => $entry->likes_count];
        });
    }
}
```

`app/Actions/Challenges/RecordImpressionsAction.php`:

```php
<?php

namespace App\Actions\Challenges;

use App\Models\ChallengeEntry;

class RecordImpressionsAction
{
    private const MAX_PER_BATCH = 20;

    /** @param array<int, mixed> $entryIds */
    public function __invoke(array $entryIds): void
    {
        $ids = collect($entryIds)
            ->filter(fn ($id): bool => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->take(self::MAX_PER_BATCH)
            ->values()
            ->all();

        if ($ids === []) {
            return;
        }

        ChallengeEntry::query()
            ->whereIn('id', $ids)
            ->where('status', ChallengeEntry::STATUS_READY)
            ->increment('impressions_count');
    }
}
```

`app/Actions/Challenges/PostEntryCommentAction.php`:

```php
<?php

namespace App\Actions\Challenges;

use App\Models\ChallengeEntry;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PostEntryCommentAction
{
    public function __invoke(User $user, ChallengeEntry $entry, string $body): Comment
    {
        $body = trim($body);

        if ($body === '' || mb_strlen($body) > (int) config('versus.challenges.comment_max_length')) {
            throw ValidationException::withMessages(['body' => __('challenges.comment_required')]);
        }
        if ($entry->status !== ChallengeEntry::STATUS_READY) {
            throw ValidationException::withMessages(['entry' => __('challenges.entry_not_votable')]);
        }

        return Comment::create([
            'user_id' => $user->id,
            'challenge_entry_id' => $entry->id,
            'body' => $body,
        ]);
    }
}
```

- [ ] **Step 5: Keep battle-only comment queries battle-only**

`app/Livewire/ProfilePage.php` in `loadComments()` — add `->whereNotNull('battle_id')` after `->where('user_id', $user->id)`.

`app/Services/Feed/FeedService.php` in `argueEvents()` — change the first query line to:

```php
        $query = Comment::query()->whereNotNull('battle_id')->with(['user', 'battle.category']);
```

- [ ] **Step 6: Update the spec**

In `docs/superpowers/specs/2026-09-15-challenges-core-design.md`, replace the `### comments` paragraph body with:

```markdown
`battle_id` becomes nullable; add nullable `challenge_entry_id` FK. Exactly one
is set (enforced in the posting actions). `CommentThread` stays battle-only — it
is coupled to token-priced likes, sides and stake support. Video comments use a
slim flat thread in the feed's comments sheet (list + post via
`PostEntryCommentAction`; no likes, replies or notifications in sub-project 1).
Battle-only comment queries (`ProfilePage`, `FeedService`) filter
`whereNotNull('battle_id')`.
```

And in the product-decisions defaults sentence, replace "Comments reuse `CommentThread`." with "Comments are a slim flat thread per video."

- [ ] **Step 7: Run tests**

Run: `php artisan test --filter="EntryInteractionsTest|Feed|Profile"` → PASS.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_09_15_100100_add_challenge_entry_id_to_comments_table.php app/Models/Comment.php app/Actions/Challenges/ToggleEntryLikeAction.php app/Actions/Challenges/RecordImpressionsAction.php app/Actions/Challenges/PostEntryCommentAction.php app/Livewire/ProfilePage.php app/Services/Feed/FeedService.php docs/superpowers/specs/2026-09-15-challenges-core-design.md tests/Feature/Challenges/EntryInteractionsTest.php
git commit -m "feat(challenges): likes, impressions and video comments

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 12: Feed scoring and upload cleanup commands

**Files:**
- Create: `app/Services/Challenges/FeedScorer.php`
- Create: `app/Console/Commands/ScoreChallengeFeedCommand.php`, `app/Console/Commands/CleanupChallengeUploadsCommand.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Challenges/FeedScoringAndCleanupTest.php`

**Interfaces:**
- Consumes: `MarkEntryFailedAction` (Task 6), `UploadStore::dir()`.
- Produces:
  - `FeedScorer::score(Challenge $challenge, int $recentActivity, CarbonInterface $now): float`
  - `FeedScorer::recalculate(CarbonInterface $now): int` (number of active challenges scored)
  - Artisan `challenges:score-feed` (every minute), `challenges:cleanup-uploads` (daily): deletes `uploads/*` dirs older than 24 h and fails entries stuck in `processing` for > 24 h via `MarkEntryFailedAction` with `reason_generic`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeVote;
use App\Models\User;
use App\Services\Challenges\FeedScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FeedScoringAndCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresher_and_busier_and_ending_soon_score_higher(): void
    {
        $scorer = app(FeedScorer::class);
        $now = now();

        $fresh = Challenge::factory()->make(['created_at' => $now, 'ends_at' => $now->copy()->addDays(2)]);
        $old = Challenge::factory()->make(['created_at' => $now->copy()->subDays(3), 'ends_at' => $now->copy()->addDays(2)]);
        $endingSoon = Challenge::factory()->make(['created_at' => $now->copy()->subDays(3), 'ends_at' => $now->copy()->addHour()]);

        $this->assertGreaterThan($scorer->score($old, 0, $now), $scorer->score($fresh, 0, $now));
        $this->assertGreaterThan($scorer->score($old, 0, $now), $scorer->score($old, 10, $now));
        $this->assertGreaterThan($scorer->score($old, 0, $now), $scorer->score($endingSoon, 0, $now));
    }

    public function test_recalculate_counts_recent_responses_and_votes(): void
    {
        $quiet = Challenge::factory()->withOriginal()->create(['created_at' => now()->subDay()]);
        $busy = Challenge::factory()->withOriginal()->create(['created_at' => now()->subDay()]);
        $entry = ChallengeEntry::factory()->for($busy)->create();
        ChallengeVote::create(['user_id' => User::factory()->create()->id, 'challenge_id' => $busy->id, 'entry_id' => $entry->id]);

        $this->artisan('challenges:score-feed')->assertSuccessful();

        $this->assertGreaterThan($quiet->fresh()->feed_score, $busy->fresh()->feed_score);
    }

    public function test_cleanup_removes_stale_uploads_and_fails_stuck_entries(): void
    {
        Storage::fake('local');
        Notification::fake();
        Storage::disk('local')->put('uploads/11111111-1111-1111-1111-111111111111/source', 'old');
        Storage::disk('local')->put('uploads/22222222-2222-2222-2222-222222222222/source', 'new');
        touch(Storage::disk('local')->path('uploads/11111111-1111-1111-1111-111111111111'), now()->subDays(2)->getTimestamp());

        $challenge = Challenge::factory()->processing()->create();
        $stuck = ChallengeEntry::factory()->original()->processing()->create([
            'challenge_id' => $challenge->id,
            'user_id' => $challenge->user_id,
            'created_at' => now()->subDays(2),
        ]);

        $this->artisan('challenges:cleanup-uploads')->assertSuccessful();

        Storage::disk('local')->assertMissing('uploads/11111111-1111-1111-1111-111111111111');
        Storage::disk('local')->assertExists('uploads/22222222-2222-2222-2222-222222222222/source');
        $this->assertSame(ChallengeEntry::STATUS_FAILED, $stuck->fresh()->status);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=FeedScoringAndCleanupTest`
Expected: FAIL with `Class "App\Services\Challenges\FeedScorer" not found`.

- [ ] **Step 3: Scorer**

`app/Services/Challenges/FeedScorer.php`:

```php
<?php

namespace App\Services\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeVote;
use Carbon\CarbonInterface;

class FeedScorer
{
    public function score(Challenge $challenge, int $recentActivity, CarbonInterface $now): float
    {
        /** @var array{freshness_half_life_hours: int|float, weight_freshness: float, weight_activity: float, ending_soon_hours: int|float, weight_ending_soon: float} $cfg */
        $cfg = config('versus.challenges.feed');

        $ageHours = abs(($challenge->created_at ?? $now)->diffInMinutes($now)) / 60;
        $freshness = 0.5 ** ($ageHours / $cfg['freshness_half_life_hours']);

        $activity = log1p(max(0, $recentActivity));

        $endingSoon = 0.0;
        if ($challenge->ends_at !== null) {
            $hoursLeft = $now->diffInMinutes($challenge->ends_at) / 60;
            $endingSoon = ($hoursLeft >= 0 && $hoursLeft <= $cfg['ending_soon_hours']) ? 1.0 : 0.0;
        }

        return round(
            $cfg['weight_freshness'] * $freshness
            + $cfg['weight_activity'] * $activity
            + $cfg['weight_ending_soon'] * $endingSoon,
            6,
        );
    }

    public function recalculate(CarbonInterface $now): int
    {
        $challenges = Challenge::where('status', Challenge::STATUS_ACTIVE)->get();
        if ($challenges->isEmpty()) {
            return 0;
        }

        $ids = $challenges->modelKeys();
        $since = $now->copy()->subHours((int) config('versus.challenges.feed.activity_window_hours'));

        $responses = ChallengeEntry::query()
            ->whereIn('challenge_id', $ids)
            ->where('is_original', false)
            ->where('status', ChallengeEntry::STATUS_READY)
            ->where('created_at', '>=', $since)
            ->selectRaw('challenge_id, COUNT(*) as aggregate')
            ->groupBy('challenge_id')
            ->pluck('aggregate', 'challenge_id');

        $votes = ChallengeVote::query()
            ->whereIn('challenge_id', $ids)
            ->where('updated_at', '>=', $since)
            ->selectRaw('challenge_id, COUNT(*) as aggregate')
            ->groupBy('challenge_id')
            ->pluck('aggregate', 'challenge_id');

        foreach ($challenges as $challenge) {
            $activity = (int) ($responses[$challenge->id] ?? 0) + (int) ($votes[$challenge->id] ?? 0);
            $challenge->feed_score = $this->score($challenge, $activity, $now);
            $challenge->saveQuietly();
        }

        return $challenges->count();
    }
}
```

- [ ] **Step 4: Commands and schedule**

`app/Console/Commands/ScoreChallengeFeedCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Services\Challenges\FeedScorer;
use Illuminate\Console\Command;

class ScoreChallengeFeedCommand extends Command
{
    protected $signature = 'challenges:score-feed';

    protected $description = 'Recompute feed_score for active challenges.';

    public function handle(FeedScorer $scorer): int
    {
        $this->info('Scored '.$scorer->recalculate(now()).' challenges.');

        return self::SUCCESS;
    }
}
```

`app/Console/Commands/CleanupChallengeUploadsCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Actions\Challenges\MarkEntryFailedAction;
use App\Models\ChallengeEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupChallengeUploadsCommand extends Command
{
    protected $signature = 'challenges:cleanup-uploads';

    protected $description = 'Delete abandoned chunked uploads and fail entries stuck in processing.';

    public function handle(MarkEntryFailedAction $markFailed): int
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subDay()->getTimestamp();

        foreach ($disk->directories('uploads') as $dir) {
            $mtime = @filemtime($disk->path($dir));
            if ($mtime !== false && $mtime < $cutoff) {
                $disk->deleteDirectory($dir);
            }
        }

        ChallengeEntry::query()
            ->where('status', ChallengeEntry::STATUS_PROCESSING)
            ->where('created_at', '<', now()->subDay())
            ->each(fn (ChallengeEntry $entry) => $markFailed($entry, 'reason_generic'));

        return self::SUCCESS;
    }
}
```

Append to `routes/console.php`:

```php
Schedule::command('challenges:score-feed')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('challenges:cleanup-uploads')
    ->daily();
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=FeedScoringAndCleanupTest` → PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Challenges/FeedScorer.php app/Console/Commands/ScoreChallengeFeedCommand.php app/Console/Commands/CleanupChallengeUploadsCommand.php routes/console.php tests/Feature/Challenges/FeedScoringAndCleanupTest.php
git commit -m "feat(challenges): feed scoring and upload cleanup schedules

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 13: Carousel composition and feed query

**Files:**
- Create: `app/Services/Challenges/ChallengeCarousel.php`, `app/Services/Challenges/ChallengeFeedQuery.php`
- Test: `tests/Feature/Challenges/CarouselAndFeedQueryTest.php`

**Interfaces:**
- Produces:
  - `ChallengeCarousel::responses(Challenge $challenge, ?int $focusEntryId = null): Collection<int, ChallengeEntry>` — ready responses (with `user`) in carousel order: top `carousel_top_by_votes` by `votes_count` desc, `submitted_at` asc, `id` asc; then `carousel_fresh_slots` drawn at random from the `2 × fresh_slots` lowest-`impressions_count` remaining. A focus entry outside that set is prepended.
  - `ChallengeFeedQuery::next(?User $viewer, array $excludeIds, int $limit, array $guestViewedIds = []): EloquentCollection<int, Challenge>` — active, `ends_at > now()`, not excluded; unseen first, then `feed_score` desc, `id` desc; eager-loads `user`, `original`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeView;
use App\Models\User;
use App\Services\Challenges\ChallengeCarousel;
use App\Services\Challenges\ChallengeFeedQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarouselAndFeedQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_seven_by_votes_then_three_low_impression_responses(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $leaders = collect(range(1, 7))->map(fn (int $i) => ChallengeEntry::factory()->for($challenge)->create([
            'votes_count' => 100 - $i,
            'impressions_count' => 1000,
        ]));
        $unseen = collect(range(1, 3))->map(fn () => ChallengeEntry::factory()->for($challenge)->create([
            'votes_count' => 0,
            'impressions_count' => 0,
        ]));
        // Pool for the 3 rotating slots is the 6 lowest-impression non-leaders: 3 unseen + 3 of these.
        $seen = ChallengeEntry::factory()->for($challenge)->count(3)->create(['votes_count' => 0, 'impressions_count' => 500]);
        // Never in the pool: more impressions than the 6 lowest.
        $overexposed = ChallengeEntry::factory()->for($challenge)->create(['votes_count' => 0, 'impressions_count' => 9000]);
        ChallengeEntry::factory()->for($challenge)->processing()->create();

        $list = app(ChallengeCarousel::class)->responses($challenge);

        $this->assertSame($leaders->pluck('id')->all(), $list->take(7)->pluck('id')->all());
        $this->assertCount(10, $list);
        $this->assertCount(10, $list->pluck('id')->unique());
        $this->assertFalse($list->contains('is_original', true));
        $pool = $unseen->pluck('id')->merge($seen->pluck('id'));
        $this->assertSame([], $list->slice(7)->pluck('id')->diff($pool)->values()->all());
        $this->assertFalse($list->contains('id', $overexposed->id));
    }

    public function test_fewer_than_ten_responses_returns_all_ready(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        ChallengeEntry::factory()->for($challenge)->count(4)->create();

        $this->assertCount(4, app(ChallengeCarousel::class)->responses($challenge));
    }

    public function test_focus_entry_outside_the_carousel_is_prepended(): void
    {
        config(['versus.challenges.carousel_top_by_votes' => 1, 'versus.challenges.carousel_fresh_slots' => 0]);
        $challenge = Challenge::factory()->withOriginal()->create();
        ChallengeEntry::factory()->for($challenge)->create(['votes_count' => 5]);
        $focus = ChallengeEntry::factory()->for($challenge)->create(['votes_count' => 0]);

        $list = app(ChallengeCarousel::class)->responses($challenge, $focus->id);

        $this->assertSame($focus->id, $list->first()->id);
        $this->assertCount(2, $list);
    }

    public function test_feed_shows_open_challenges_unseen_first_then_by_score(): void
    {
        $viewer = User::factory()->create();
        $seenTop = Challenge::factory()->create(['feed_score' => 9]);
        $unseenLow = Challenge::factory()->create(['feed_score' => 1]);
        $unseenHigh = Challenge::factory()->create(['feed_score' => 5]);
        Challenge::factory()->closed()->create(['feed_score' => 99]);
        Challenge::factory()->create(['feed_score' => 99, 'ends_at' => now()->subMinute()]);
        ChallengeView::create(['user_id' => $viewer->id, 'challenge_id' => $seenTop->id, 'viewed_at' => now()]);

        $ids = app(ChallengeFeedQuery::class)->next($viewer, [], 10)->pluck('id')->all();

        $this->assertSame([$unseenHigh->id, $unseenLow->id, $seenTop->id], $ids);
    }

    public function test_feed_excludes_already_loaded_and_uses_guest_views(): void
    {
        $a = Challenge::factory()->create(['feed_score' => 9]);
        $b = Challenge::factory()->create(['feed_score' => 5]);
        $c = Challenge::factory()->create(['feed_score' => 1]);

        $ids = app(ChallengeFeedQuery::class)->next(null, [$b->id], 10, [$a->id])->pluck('id')->all();

        $this->assertSame([$c->id, $a->id], $ids);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CarouselAndFeedQueryTest`
Expected: FAIL with `Class "App\Services\Challenges\ChallengeCarousel" not found`.

- [ ] **Step 3: Implement**

`app/Services/Challenges/ChallengeCarousel.php`:

```php
<?php

namespace App\Services\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ChallengeCarousel
{
    /** @return Collection<int, ChallengeEntry> */
    public function responses(Challenge $challenge, ?int $focusEntryId = null): Collection
    {
        $top = (int) config('versus.challenges.carousel_top_by_votes');
        $fresh = (int) config('versus.challenges.carousel_fresh_slots');

        $leaders = $top > 0
            ? $this->base($challenge)
                ->orderByDesc('votes_count')
                ->orderBy('submitted_at')
                ->orderBy('id')
                ->limit($top)
                ->get()
            : collect();

        $rotating = $fresh > 0
            ? $this->base($challenge)
                ->whereNotIn('id', $leaders->pluck('id')->all())
                ->orderBy('impressions_count')
                ->orderBy('id')
                ->limit($fresh * 2)
                ->get()
                ->shuffle()
                ->take($fresh)
            : collect();

        /** @var Collection<int, ChallengeEntry> $list */
        $list = collect($leaders->all())->concat($rotating->all())->values();

        if ($focusEntryId !== null && ! $list->contains('id', $focusEntryId)) {
            $focus = $this->base($challenge)->whereKey($focusEntryId)->first();
            if ($focus !== null) {
                $list = $list->prepend($focus)->values();
            }
        }

        return $list;
    }

    /** @return Builder<ChallengeEntry> */
    private function base(Challenge $challenge): Builder
    {
        return ChallengeEntry::query()
            ->where('challenge_id', $challenge->id)
            ->where('is_original', false)
            ->where('status', ChallengeEntry::STATUS_READY)
            ->with('user');
    }
}
```

`app/Services/Challenges/ChallengeFeedQuery.php`:

```php
<?php

namespace App\Services\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeView;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ChallengeFeedQuery
{
    /**
     * @param  array<int, int>  $excludeIds
     * @param  array<int, int>  $guestViewedIds
     * @return Collection<int, Challenge>
     */
    public function next(?User $viewer, array $excludeIds, int $limit, array $guestViewedIds = []): Collection
    {
        $viewedIds = $viewer !== null
            ? ChallengeView::where('user_id', $viewer->id)->pluck('challenge_id')->all()
            : $guestViewedIds;
        $viewedIds = array_values(array_unique(array_map('intval', $viewedIds)));

        $query = Challenge::query()
            ->where('status', Challenge::STATUS_ACTIVE)
            ->where('ends_at', '>', now())
            ->whereNotIn('id', array_map('intval', $excludeIds));

        if ($viewedIds !== []) {
            // Integers only (cast above), so inlining is injection-safe and portable across SQLite/Postgres.
            $query->orderByRaw('CASE WHEN id IN ('.implode(',', $viewedIds).') THEN 1 ELSE 0 END');
        }

        return $query
            ->orderByDesc('feed_score')
            ->orderByDesc('id')
            ->limit($limit)
            ->with(['user', 'original'])
            ->get();
    }
}
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=CarouselAndFeedQueryTest` → PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Challenges/ChallengeCarousel.php app/Services/Challenges/ChallengeFeedQuery.php tests/Feature/Challenges/CarouselAndFeedQueryTest.php
git commit -m "feat(challenges): carousel composition and feed ordering

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 14: Challenge feed page

**Files:**
- Create: `app/Services/Challenges/ChallengeFeedPresenter.php`
- Create: `app/Livewire/ChallengeFeed.php`, `resources/views/livewire/challenge-feed.blade.php`
- Create: `resources/js/challenges/feed.js`; Modify: `resources/js/app.js`
- Modify: `routes/web.php`
- Test: `tests/Feature/Challenges/ChallengeFeedPresenterTest.php`, `tests/Feature/Challenges/ChallengeFeedPageTest.php`

**Interfaces:**
- Consumes: `ChallengeCarousel`, `ChallengeFeedQuery`, actions from Tasks 8, 9, 11.
- Produces:
  - Routes: `GET /challenges` → `challenges.index`; `GET /c/{challenge:slug}` → `challenges.show` (query `entry`). Temporary named routes, replaced later: `challenges.create` (`/challenges/create`, Task 15), `challenges.respond` (`/c/{challenge:slug}/respond`, Task 15), `challenges.mine` (`/my-challenges`, Task 16).
  - `ChallengeFeedPresenter::present(Challenge $challenge, ?User $viewer, ?int $focusEntryId = null): array` with keys `id, slug, title, rules, author_name, ends_at (ISO-8601|null), is_open, is_own, accepted, my_entry_id, my_vote_entry_id, entries_count, winner_entry_id, winner_name, respond_url, focus_index, slides`; each slide `entry_id, is_original, video_url, poster_url, author{name, username, avatar_url, profile_url}, likes_count, liked, votes_count, comments_count, is_mine, is_winner, share_url`. Slide 0 is the original.
  - `ChallengeFeed` renderless methods returning arrays: `loadMore(): list<array>`, `markViewed(int)`, `recordImpressions(array)`, `toggleLike(int): array`, `vote(int): array`, `accept(int): array`, `comments(int): list<array>`, `postComment(int, string): array`, `allResponses(int): list<array>`, `dismissSwipeHint()`. Failure shape: `['redirect' => url]` for guests, `['error' => message]` for domain errors.
  - Alpine component `challengeFeed({...})`.

- [ ] **Step 1: Write the failing presenter test**

`tests/Feature/Challenges/ChallengeFeedPresenterTest.php`:

```php
<?php

namespace Tests\Feature\Challenges;

use App\Actions\Challenges\CastChallengeVoteAction;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeParticipant;
use App\Models\EntryLike;
use App\Models\User;
use App\Services\Challenges\ChallengeFeedPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChallengeFeedPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_original_first_and_no_personal_state(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $response = ChallengeEntry::factory()->for($challenge)->create();

        $data = app(ChallengeFeedPresenter::class)->present($challenge, null);

        $this->assertTrue($data['slides'][0]['is_original']);
        $this->assertSame($response->id, $data['slides'][1]['entry_id']);
        $this->assertTrue($data['is_open']);
        $this->assertFalse($data['is_own']);
        $this->assertNull($data['my_vote_entry_id']);
        $this->assertSame(route('challenges.show', ['challenge' => $challenge->slug, 'entry' => $response->id]), $data['slides'][1]['share_url']);
    }

    public function test_viewer_state_vote_like_accept_and_own_entry(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $viewer = User::factory()->create();
        $mine = ChallengeEntry::factory()->for($challenge)->for($viewer)->create();
        $other = ChallengeEntry::factory()->for($challenge)->create();
        ChallengeParticipant::create(['user_id' => $viewer->id, 'challenge_id' => $challenge->id, 'accepted_at' => now()]);
        app(CastChallengeVoteAction::class)($viewer, $other);
        EntryLike::create(['user_id' => $viewer->id, 'entry_id' => $other->id]);

        $data = app(ChallengeFeedPresenter::class)->present($challenge, $viewer);
        $slides = collect($data['slides'])->keyBy('entry_id');

        $this->assertTrue($data['accepted']);
        $this->assertSame($mine->id, $data['my_entry_id']);
        $this->assertSame($other->id, $data['my_vote_entry_id']);
        $this->assertTrue($slides[$mine->id]['is_mine']);
        $this->assertTrue($slides[$other->id]['liked']);
    }

    public function test_closed_challenge_exposes_winner_and_focus_index(): void
    {
        $challenge = Challenge::factory()->closed()->withOriginal()->create();
        $winner = ChallengeEntry::factory()->for($challenge)->create(['votes_count' => 3]);
        $challenge->update(['winner_entry_id' => $winner->id]);

        $data = app(ChallengeFeedPresenter::class)->present($challenge->fresh(), null, $winner->id);

        $this->assertFalse($data['is_open']);
        $this->assertSame($winner->user->name, $data['winner_name']);
        $this->assertSame(1, $data['focus_index']);
        $this->assertTrue($data['slides'][1]['is_winner']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ChallengeFeedPresenterTest`
Expected: FAIL with `Class "App\Services\Challenges\ChallengeFeedPresenter" not found`.

- [ ] **Step 3: Presenter**

`app/Services/Challenges/ChallengeFeedPresenter.php`:

```php
<?php

namespace App\Services\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeParticipant;
use App\Models\ChallengeVote;
use App\Models\Comment;
use App\Models\EntryLike;
use App\Models\User;

class ChallengeFeedPresenter
{
    public function __construct(private readonly ChallengeCarousel $carousel) {}

    /** @return array<string, mixed> */
    public function present(Challenge $challenge, ?User $viewer, ?int $focusEntryId = null): array
    {
        $challenge->loadMissing(['user', 'original.user', 'winnerEntry.user']);

        /** @var list<ChallengeEntry> $entries */
        $entries = array_values(array_filter([
            $challenge->original,
            ...$this->carousel->responses($challenge, $focusEntryId)->all(),
        ]));
        $ids = array_map(fn (ChallengeEntry $e): int => $e->id, $entries);

        $likedIds = $viewer !== null
            ? EntryLike::where('user_id', $viewer->id)->whereIn('entry_id', $ids)->pluck('entry_id')->all()
            : [];
        $commentCounts = Comment::query()
            ->whereIn('challenge_entry_id', $ids)
            ->selectRaw('challenge_entry_id, COUNT(*) as aggregate')
            ->groupBy('challenge_entry_id')
            ->pluck('aggregate', 'challenge_entry_id');

        $myEntryId = $viewer !== null
            ? ChallengeEntry::where('challenge_id', $challenge->id)->where('user_id', $viewer->id)->value('id')
            : null;
        $myVoteEntryId = $viewer !== null
            ? ChallengeVote::where('challenge_id', $challenge->id)->where('user_id', $viewer->id)->value('entry_id')
            : null;
        $accepted = $viewer !== null
            && ChallengeParticipant::where('challenge_id', $challenge->id)->where('user_id', $viewer->id)->exists();

        $slides = array_map(fn (ChallengeEntry $entry): array => [
            'entry_id' => $entry->id,
            'is_original' => $entry->is_original,
            'video_url' => $entry->videoUrl(),
            'poster_url' => $entry->posterUrl(),
            'author' => [
                'name' => $entry->user->name,
                'username' => $entry->user->username,
                'avatar_url' => $entry->user->avatarUrl(),
                'profile_url' => route('profile.show', $entry->user),
            ],
            'likes_count' => $entry->likes_count,
            'liked' => in_array($entry->id, $likedIds, true),
            'votes_count' => $entry->votes_count,
            'comments_count' => (int) ($commentCounts[$entry->id] ?? 0),
            'is_mine' => $viewer !== null && $entry->user_id === $viewer->id,
            'is_winner' => $challenge->winner_entry_id === $entry->id,
            'share_url' => route('challenges.show', ['challenge' => $challenge->slug, 'entry' => $entry->id]),
        ], $entries);

        $focusIndex = $focusEntryId !== null ? array_search($focusEntryId, $ids, true) : false;

        return [
            'id' => $challenge->id,
            'slug' => $challenge->slug,
            'title' => $challenge->title,
            'rules' => $challenge->rules,
            'author_name' => $challenge->user->name,
            'ends_at' => $challenge->ends_at?->toIso8601String(),
            'is_open' => $challenge->isOpen(),
            'is_own' => $viewer !== null && $challenge->user_id === $viewer->id,
            'accepted' => $accepted,
            'my_entry_id' => $myEntryId !== null ? (int) $myEntryId : null,
            'my_vote_entry_id' => $myVoteEntryId !== null ? (int) $myVoteEntryId : null,
            'entries_count' => $challenge->entries_count,
            'winner_entry_id' => $challenge->winner_entry_id,
            'winner_name' => $challenge->winnerEntry?->user?->name,
            'respond_url' => route('challenges.respond', $challenge->slug),
            'focus_index' => $focusIndex === false ? 0 : $focusIndex,
            'slides' => $slides,
        ];
    }
}
```

- [ ] **Step 4: Routes**

In `routes/web.php` add imports `use App\Livewire\ChallengeFeed;` and add, after the `/leaderboard` route line:

```php
Route::get('/challenges', ChallengeFeed::class)->name('challenges.index');
// Replaced by the real form in Task 15.
Route::middleware(['auth', 'verified'])->get('/challenges/create', fn () => abort(404))->name('challenges.create');
Route::middleware(['auth', 'verified'])->get('/c/{challenge:slug}/respond', fn () => abort(404))->name('challenges.respond');
Route::get('/c/{challenge:slug}', ChallengeFeed::class)->name('challenges.show');
```

and inside the `auth`+`verified` group:

```php
    // Replaced by the real page in Task 16.
    Route::get('/my-challenges', fn () => abort(404))->name('challenges.mine');
```

- [ ] **Step 5: Run presenter test**

Run: `php artisan test --filter=ChallengeFeedPresenterTest`
Expected: FAIL only because `App\Livewire\ChallengeFeed` does not exist yet (route file import) — continue to Step 6.

- [ ] **Step 6: Write the failing page test**

`tests/Feature/Challenges/ChallengeFeedPageTest.php`:

```php
<?php

namespace Tests\Feature\Challenges;

use App\Livewire\ChallengeFeed;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeParticipant;
use App\Models\ChallengeView;
use App\Models\ChallengeVote;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChallengeFeedPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_the_feed_and_deep_link(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create(['title' => 'Kickflip clean']);
        $entry = ChallengeEntry::factory()->for($challenge)->create();

        $this->get(route('challenges.index'))->assertOk()->assertSee('Kickflip clean');
        $this->get(route('challenges.show', ['challenge' => $challenge->slug, 'entry' => $entry->id]))->assertOk();

        // @js() escapes quotes, so assert on component state rather than raw HTML.
        Livewire::withQueryParams(['entry' => $entry->id])
            ->test(ChallengeFeed::class, ['challenge' => $challenge])
            ->assertSet('initialSlides.0.id', $challenge->id)
            ->assertSet('initialSlides.0.focus_index', 1);
    }

    public function test_deep_link_opens_closed_challenge_even_though_feed_hides_it(): void
    {
        $closed = Challenge::factory()->closed()->withOriginal()->create(['title' => 'Old riff']);

        $this->get(route('challenges.index'))->assertDontSee('Old riff');
        $this->get(route('challenges.show', $closed->slug))->assertOk()->assertSee('Old riff');
    }

    public function test_guest_actions_do_not_write(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();

        Livewire::test(ChallengeFeed::class)
            ->call('vote', $challenge->original->id)
            ->call('accept', $challenge->id)
            ->call('toggleLike', $challenge->original->id);

        $this->assertSame(0, ChallengeVote::count());
        $this->assertSame(0, ChallengeParticipant::count());
    }

    public function test_user_can_vote_accept_like_comment_and_mark_viewed(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ChallengeFeed::class)
            ->call('vote', $challenge->original->id)
            ->call('accept', $challenge->id)
            ->call('toggleLike', $challenge->original->id)
            ->call('postComment', $challenge->original->id, 'great')
            ->call('markViewed', $challenge->id)
            ->call('recordImpressions', [$challenge->original->id])
            ->call('dismissSwipeHint');

        $this->assertSame(1, $challenge->original->fresh()->votes_count);
        $this->assertSame(1, $challenge->original->fresh()->likes_count);
        $this->assertSame(1, $challenge->original->fresh()->impressions_count);
        $this->assertTrue(ChallengeParticipant::where('user_id', $user->id)->exists());
        $this->assertTrue(ChallengeView::where('user_id', $user->id)->exists());
        $this->assertSame('great', Comment::where('challenge_entry_id', $challenge->original->id)->value('body'));
        $this->assertNotNull($user->fresh()->swipe_hint_seen_at);
    }

    public function test_load_more_skips_already_loaded_challenges(): void
    {
        Challenge::factory()->withOriginal()->count(7)->create();

        $component = Livewire::test(ChallengeFeed::class);
        $this->assertCount(5, $component->get('loadedIds'));

        $component->call('loadMore');
        $this->assertCount(7, $component->get('loadedIds'));
    }
}
```

- [ ] **Step 7: Component**

`app/Livewire/ChallengeFeed.php`:

```php
<?php

namespace App\Livewire;

use App\Actions\Challenges\AcceptChallengeAction;
use App\Actions\Challenges\CastChallengeVoteAction;
use App\Actions\Challenges\PostEntryCommentAction;
use App\Actions\Challenges\RecordImpressionsAction;
use App\Actions\Challenges\ToggleEntryLikeAction;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeView;
use App\Models\Comment;
use App\Models\User;
use App\Services\Challenges\ChallengeFeedPresenter;
use App\Services\Challenges\ChallengeFeedQuery;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;

#[Layout('layouts.app')]
class ChallengeFeed extends Component
{
    private const BATCH = 5;

    private const SESSION_VIEWS = 'challenge_views';

    #[Locked]
    public ?int $focusChallengeId = null;

    #[Locked]
    public ?int $focusEntryId = null;

    /** @var list<int> */
    #[Locked]
    public array $loadedIds = [];

    /** @var list<array<string, mixed>> */
    public array $initialSlides = [];

    public function mount(?Challenge $challenge = null): void
    {
        if ($challenge !== null && $challenge->exists) {
            $this->focusChallengeId = $challenge->id;
            $entry = request()->integer('entry');
            $this->focusEntryId = $entry > 0 ? $entry : null;
        }

        $this->initialSlides = $this->batch();
    }

    /** @return list<array<string, mixed>> */
    #[Renderless]
    public function loadMore(): array
    {
        return $this->batch();
    }

    #[Renderless]
    public function markViewed(int $challengeId): void
    {
        if (! Challenge::whereKey($challengeId)->exists()) {
            return;
        }

        $user = $this->user();
        if ($user !== null) {
            ChallengeView::updateOrCreate(
                ['user_id' => $user->id, 'challenge_id' => $challengeId],
                ['viewed_at' => now()],
            );

            return;
        }

        $views = array_map('intval', (array) session(self::SESSION_VIEWS, []));
        session([self::SESSION_VIEWS => array_values(array_unique([...$views, $challengeId]))]);
    }

    /** @param array<int, mixed> $entryIds */
    #[Renderless]
    public function recordImpressions(array $entryIds, RecordImpressionsAction $record): void
    {
        $record($entryIds);
    }

    /** @return array<string, mixed> */
    #[Renderless]
    public function toggleLike(int $entryId, ToggleEntryLikeAction $toggle): array
    {
        return $this->guarded(fn (User $user): array => $toggle($user, ChallengeEntry::findOrFail($entryId)));
    }

    /** @return array<string, mixed> */
    #[Renderless]
    public function vote(int $entryId, CastChallengeVoteAction $cast): array
    {
        return $this->guarded(function (User $user) use ($entryId, $cast): array {
            $vote = $cast($user, ChallengeEntry::findOrFail($entryId));

            return [
                'my_vote_entry_id' => $vote->entry_id,
                'counts' => ChallengeEntry::where('challenge_id', $vote->challenge_id)->pluck('votes_count', 'id')->all(),
            ];
        });
    }

    /** @return array<string, mixed> */
    #[Renderless]
    public function accept(int $challengeId, AcceptChallengeAction $accept): array
    {
        return $this->guarded(function (User $user) use ($challengeId, $accept): array {
            $challenge = Challenge::findOrFail($challengeId);
            $accept($user, $challenge);

            return ['respond_url' => route('challenges.respond', $challenge->slug)];
        });
    }

    /** @return list<array<string, mixed>> */
    #[Renderless]
    public function comments(int $entryId): array
    {
        return Comment::query()
            ->where('challenge_entry_id', $entryId)
            ->with('user:id,name,avatar_path')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Comment $comment): array => $this->commentRow($comment))
            ->all();
    }

    /** @return array<string, mixed> */
    #[Renderless]
    public function postComment(int $entryId, string $body, PostEntryCommentAction $post): array
    {
        return $this->guarded(function (User $user) use ($entryId, $body, $post): array {
            $comment = $post($user, ChallengeEntry::findOrFail($entryId), $body);

            return ['comment' => $this->commentRow($comment->load('user:id,name,avatar_path'))];
        });
    }

    /** @return list<array<string, mixed>> */
    #[Renderless]
    public function allResponses(int $challengeId): array
    {
        $challenge = Challenge::findOrFail($challengeId);

        return ChallengeEntry::query()
            ->where('challenge_id', $challenge->id)
            ->where('is_original', false)
            ->where('status', ChallengeEntry::STATUS_READY)
            ->with('user:id,name')
            ->orderByDesc('votes_count')
            ->orderBy('submitted_at')
            ->get()
            ->map(fn (ChallengeEntry $entry): array => [
                'entry_id' => $entry->id,
                'poster_url' => $entry->posterUrl(),
                'name' => $entry->user->name,
                'votes_count' => $entry->votes_count,
                'url' => route('challenges.show', ['challenge' => $challenge->slug, 'entry' => $entry->id]),
            ])
            ->all();
    }

    #[Renderless]
    public function dismissSwipeHint(): void
    {
        $user = $this->user();
        if ($user !== null && $user->swipe_hint_seen_at === null) {
            $user->forceFill(['swipe_hint_seen_at' => now()])->save();
        }
    }

    public function render(): View
    {
        return view('livewire.challenge-feed', [
            'hintSeen' => $this->user()?->swipe_hint_seen_at !== null,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function batch(): array
    {
        $presenter = app(ChallengeFeedPresenter::class);
        $viewer = $this->user();
        $slides = [];

        if ($this->loadedIds === [] && $this->focusChallengeId !== null) {
            $focus = Challenge::find($this->focusChallengeId);
            if ($focus !== null) {
                $slides[] = $presenter->present($focus, $viewer, $this->focusEntryId);
                $this->loadedIds[] = $focus->id;
            }
        }

        $challenges = app(ChallengeFeedQuery::class)->next(
            $viewer,
            $this->loadedIds,
            self::BATCH - count($slides),
            array_map('intval', (array) session(self::SESSION_VIEWS, [])),
        );

        foreach ($challenges as $challenge) {
            $slides[] = $presenter->present($challenge, $viewer);
            $this->loadedIds[] = $challenge->id;
        }

        return $slides;
    }

    /**
     * @param  Closure(User): array<string, mixed>  $fn
     * @return array<string, mixed>
     */
    private function guarded(Closure $fn): array
    {
        $user = $this->user();
        if ($user === null) {
            return ['redirect' => route('login')];
        }

        try {
            return ['ok' => true] + $fn($user);
        } catch (ValidationException $e) {
            return ['error' => (string) collect($e->errors())->flatten()->first()];
        }
    }

    /** @return array<string, mixed> */
    private function commentRow(Comment $comment): array
    {
        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'name' => $comment->user?->name,
            'avatar_url' => $comment->user?->avatarUrl(),
            'time' => $comment->created_at?->diffForHumans(),
        ];
    }

    private function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
```

> `$initialSlides` is a public property so the first render and the page test can read it; Alpine takes over from there.

- [ ] **Step 8: View**

`resources/views/livewire/challenge-feed.blade.php`:

```blade
<div x-data="challengeFeed({
        initial: @js($initialSlides),
        hintSeen: @js($hintSeen),
        guest: @js(auth()->guest()),
        loginUrl: @js(route('login')),
        i18n: @js([
            'pillChallenge' => __('challenges.pill_challenge'),
            'pillResponse' => __('challenges.pill_response'),
            'accept' => __('challenges.accept'),
            'vote' => __('challenges.vote'),
            'yourVote' => __('challenges.your_vote'),
            'yourChallenge' => __('challenges.your_challenge'),
            'myResponse' => __('challenges.my_response'),
            'moveVote' => __('challenges.move_vote_confirm'),
            'winner' => __('challenges.winner'),
            'noVotes' => __('challenges.no_votes'),
            'allResponses' => __('challenges.all_responses'),
            'inReplyTo' => __('challenges.in_reply_to'),
            'linkCopied' => __('challenges.link_copied'),
        ]),
     })"
     class="fixed inset-x-0 top-0 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] sm:bottom-0 z-30 bg-black text-white">

    <div x-ref="vertical" class="h-full overflow-y-auto snap-y snap-mandatory overscroll-contain"
         @scroll.debounce.120ms="onVerticalScroll()">

        <template x-if="challenges.length === 0">
            <div class="flex h-full flex-col items-center justify-center gap-4 px-8 text-center text-white/70">
                <p>{{ __('challenges.feed_empty') }}</p>
                @auth
                    <a href="{{ route('challenges.create') }}" class="rounded-full bg-indigo-600 px-5 py-2 text-sm font-semibold text-white">{{ __('challenges.publish') }}</a>
                @endauth
            </div>
        </template>

        <template x-for="(challenge, ci) in challenges" :key="challenge.id">
            <section class="relative h-full w-full snap-start overflow-hidden">
                {{-- Horizontal carousel: original, responses, "all responses" card --}}
                <div class="flex h-full overflow-x-auto snap-x snap-mandatory [scrollbar-width:none]"
                     :data-carousel="ci"
                     @scroll.debounce.120ms="onCarouselScroll(ci, $event.target)">
                    <template x-for="(slide, si) in challenge.slides" :key="slide.entry_id">
                        <div class="relative h-full w-full shrink-0 snap-start" @click="toggleMute()">
                            <video class="h-full w-full bg-black object-contain" playsinline loop muted preload="none"
                                   :poster="slide.poster_url" :data-src="slide.video_url" :data-ci="ci" :data-si="si"></video>
                            <span x-show="slide.is_winner" class="absolute left-4 top-16 rounded-full bg-amber-400/90 px-3 py-1 text-xs font-bold text-black">🏆</span>
                        </div>
                    </template>
                    <div x-show="challenge.entries_count > 0" class="flex h-full w-full shrink-0 snap-start items-center justify-center">
                        <button type="button" class="rounded-full border border-white/30 px-6 py-3 text-sm"
                                @click="openAll(ci)" x-text="i18n.allResponses.replace(':count', challenge.entries_count)"></button>
                    </div>
                </div>

                {{-- Overlay --}}
                <div class="pointer-events-none absolute inset-0 flex flex-col justify-between bg-gradient-to-b from-black/40 via-transparent to-black/70">
                    <div class="flex items-center justify-between p-4 pt-[max(1rem,env(safe-area-inset-top))]">
                        <span class="rounded-full border-2 bg-black/30 px-4 py-1.5 text-sm font-semibold"
                              :class="currentSlide(ci).is_original ? 'border-orange-500' : 'border-violet-500 shadow-[0_0_14px_rgba(139,92,246,.6)]'"
                              x-text="currentSlide(ci).is_original ? i18n.pillChallenge : i18n.pillResponse.replace(':n', challenge.index).replace(':total', challenge.slides.length - 1)"></span>
                    </div>

                    <div class="flex items-end gap-3 px-4 pb-4">
                        <div class="min-w-0 flex-1 space-y-2">
                            <a :href="currentSlide(ci).author.profile_url" class="pointer-events-auto flex items-center gap-2">
                                <img x-show="currentSlide(ci).author.avatar_url" :src="currentSlide(ci).author.avatar_url" class="h-9 w-9 rounded-full object-cover" alt="">
                                <span x-show="!currentSlide(ci).author.avatar_url" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-sm font-semibold" x-text="currentSlide(ci).author.name.charAt(0)"></span>
                                <span class="truncate font-semibold" x-text="currentSlide(ci).author.name"></span>
                                <span x-show="!currentSlide(ci).is_original" class="truncate text-xs text-white/60" x-text="i18n.inReplyTo.replace(':name', challenge.author_name)"></span>
                            </a>
                            <p x-show="challenge.is_open" class="text-xs text-white/70">⏱ <span x-data="countdown(challenge.ends_at)" x-init="start()" x-text="label"></span></p>
                            <button type="button" class="pointer-events-auto block w-full text-left" @click="sheetChallenge = challenge; sheet = 'rules'">
                                <span class="block text-lg font-bold leading-snug" x-text="challenge.title"></span>
                                <span class="line-clamp-2 text-sm text-white/80" x-text="challenge.rules"></span>
                            </button>
                        </div>

                        <div class="pointer-events-auto flex flex-col items-center gap-4 pb-2 text-xs">
                            <button type="button" @click.stop="like(ci)" class="flex flex-col items-center gap-1">
                                <span class="text-3xl" :class="currentSlide(ci).liked ? 'text-rose-500' : 'text-white'">♥</span>
                                <span x-text="currentSlide(ci).likes_count"></span>
                            </button>
                            <button type="button" @click.stop="openComments(ci)" class="flex flex-col items-center gap-1">
                                <span class="text-2xl">💬</span>
                                <span x-text="currentSlide(ci).comments_count"></span>
                            </button>
                            <button type="button" @click.stop="share(ci)" class="flex flex-col items-center gap-1">
                                <span class="text-2xl">↪</span>
                            </button>
                            <button type="button" @click.stop="copy(currentSlide(ci).share_url)" class="text-2xl leading-none">•••</button>
                        </div>
                    </div>

                    <div class="px-4 pb-4">
                        <template x-if="challenge.is_open">
                            <div class="pointer-events-auto flex h-14 w-full text-sm font-semibold uppercase tracking-wide">
                                <button type="button"
                                        class="-mr-2 flex-1 rounded-l-full bg-gradient-to-r from-orange-500 to-orange-400 [clip-path:polygon(0_0,100%_0,calc(100%_-_18px)_100%,0_100%)] disabled:opacity-60"
                                        :disabled="challenge.is_own"
                                        @click="accept(ci)"
                                        x-text="challenge.is_own ? i18n.yourChallenge : (challenge.my_entry_id ? i18n.myResponse : i18n.accept)"></button>
                                <button type="button"
                                        class="-ml-2 flex-1 rounded-r-full bg-gradient-to-r from-indigo-600 to-violet-600 [clip-path:polygon(18px_0,100%_0,100%_100%,0_100%)] disabled:opacity-60"
                                        :disabled="currentSlide(ci).is_mine"
                                        @click="vote(ci)"
                                        x-text="challenge.my_vote_entry_id === currentSlide(ci).entry_id ? i18n.yourVote : i18n.vote"></button>
                            </div>
                        </template>
                        <template x-if="!challenge.is_open">
                            <div class="flex h-14 items-center justify-center rounded-full bg-white/10 text-sm font-semibold"
                                 x-text="challenge.winner_name ? i18n.winner.replace(':name', challenge.winner_name) : i18n.noVotes"></div>
                        </template>
                    </div>
                </div>
            </section>
        </template>
    </div>

    {{-- One bell for the whole page: a Livewire component must not live inside <template x-for>. --}}
    @auth
        <div class="absolute right-4 top-[max(1rem,env(safe-area-inset-top))] z-40">
            <livewire:notification-bell />
        </div>
    @endauth

    {{-- First-visit swipe hint --}}
    <div x-show="hint" x-cloak x-transition.opacity @click="dismissHint()"
         class="absolute inset-x-0 top-1/2 z-40 mx-6 -translate-y-1/2 rounded-2xl bg-indigo-600/70 px-6 py-5 text-center backdrop-blur">
        <p class="text-lg font-semibold">← {{ __('challenges.swipe_hint_title') }} →</p>
        <p class="text-sm text-white/80">{{ __('challenges.swipe_hint_body') }}</p>
    </div>

    {{-- Bottom sheets --}}
    <div x-show="sheet !== null" x-cloak class="absolute inset-0 z-50 flex items-end bg-black/60" @click.self="sheet = null">
        <div class="max-h-[75%] w-full overflow-y-auto rounded-t-3xl bg-navy-900 p-5">
            <template x-if="sheet === 'rules' && sheetChallenge">
                <div class="space-y-3">
                    <h2 class="text-xl font-bold" x-text="sheetChallenge.title"></h2>
                    <p class="text-sm text-white/60" x-text="sheetChallenge.author_name"></p>
                    <p class="whitespace-pre-line text-sm" x-html="highlightTags(sheetChallenge.rules)"></p>
                    <p x-show="sheetChallenge.is_open" class="text-sm text-white/70">⏱ <span x-data="countdown(sheetChallenge.ends_at)" x-init="start()" x-text="label"></span></p>
                </div>
            </template>

            <template x-if="sheet === 'comments'">
                <div class="space-y-4">
                    <h2 class="text-lg font-bold">{{ __('challenges.comments') }}</h2>
                    <form class="flex gap-2" @submit.prevent="postComment()">
                        <input x-model="commentBody" maxlength="{{ config('versus.challenges.comment_max_length') }}"
                               class="flex-1 rounded-full border-white/10 bg-white/5 text-sm text-white"
                               placeholder="{{ __('challenges.comment_placeholder') }}">
                        <button class="rounded-full bg-indigo-600 px-4 text-sm font-semibold">{{ __('challenges.comment_send') }}</button>
                    </form>
                    <p x-show="comments.length === 0" class="text-sm text-white/50">{{ __('challenges.no_comments') }}</p>
                    <template x-for="comment in comments" :key="comment.id">
                        <div class="text-sm">
                            <span class="font-semibold" x-text="comment.name"></span>
                            <span class="text-xs text-white/40" x-text="comment.time"></span>
                            <p class="whitespace-pre-line text-white/85" x-text="comment.body"></p>
                        </div>
                    </template>
                </div>
            </template>

            <template x-if="sheet === 'all'">
                <div class="grid grid-cols-3 gap-2">
                    <template x-for="item in allResponses" :key="item.entry_id">
                        <a :href="item.url" class="relative aspect-[9/16] overflow-hidden rounded-lg bg-white/5">
                            <img :src="item.poster_url" class="h-full w-full object-cover" alt="">
                            <span class="absolute inset-x-0 bottom-0 truncate bg-black/60 px-1 text-[10px]" x-text="item.name + ' · ' + item.votes_count"></span>
                        </a>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>
```

- [ ] **Step 9: Alpine component**

`resources/js/challenges/feed.js`:

```js
const HINT_KEY = 'versus.swipeHintSeen';

const prepare = (challenge) => ({ ...challenge, index: challenge.focus_index ?? 0, viewed: false });

const escapeHtml = (text) => text.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

export default ({ initial, hintSeen, guest, loginUrl, i18n }) => ({
    challenges: initial.map(prepare),
    i18n,
    active: 0,
    muted: true,
    loading: false,
    exhausted: false,
    hint: false,
    sheet: null,
    sheetChallenge: null,
    sheetEntry: null,
    comments: [],
    commentBody: '',
    allResponses: [],
    impressionQueue: new Set(),
    impressionTimer: null,
    dwellTimer: null,

    init() {
        let seen = hintSeen;
        if (guest) {
            try { seen = localStorage.getItem(HINT_KEY) === '1'; } catch (e) { seen = false; }
        }
        this.hint = !seen && this.challenges.some((c) => c.slides.length > 1);

        this.$nextTick(() => {
            const first = this.challenges[0];
            const carousel = this.$el.querySelector('[data-carousel="0"]');
            if (first && first.index > 0 && carousel) {
                carousel.scrollLeft = carousel.clientWidth * first.index;
            }
            this.syncPlayback();
        });

        window.addEventListener('pagehide', () => this.flushImpressions());
    },

    currentSlide(ci) {
        const challenge = this.challenges[ci];

        return challenge.slides[Math.min(challenge.index, challenge.slides.length - 1)];
    },

    onVerticalScroll() {
        const el = this.$refs.vertical;
        const index = Math.round(el.scrollTop / Math.max(1, el.clientHeight));
        if (index !== this.active) {
            this.active = index;
            this.syncPlayback();
        }
        if (this.challenges.length - index <= 2) {
            this.loadMore();
        }
    },

    onCarouselScroll(ci, el) {
        const index = Math.round(el.scrollLeft / Math.max(1, el.clientWidth));
        const challenge = this.challenges[ci];
        if (index !== challenge.index) {
            challenge.index = index;
            this.syncPlayback();
        }
        if (index > 0) {
            this.dismissHint();
        }
    },

    // Only the current video plays; at most three <video> elements hold a src (iOS memory).
    syncPlayback() {
        clearTimeout(this.dwellTimer);
        const a = this.active;
        const current = this.challenges[a];
        if (!current) {
            return;
        }
        const next = this.challenges[a + 1];
        const currentKey = `${a}:${current.index}`;
        const keep = new Set([currentKey, `${a}:${current.index + 1}`, next ? `${a + 1}:${next.index}` : '']);

        this.$el.querySelectorAll('video[data-ci]').forEach((video) => {
            const key = `${video.dataset.ci}:${video.dataset.si}`;
            if (keep.has(key)) {
                if (video.getAttribute('src') !== video.dataset.src) {
                    video.preload = key === currentKey ? 'auto' : 'metadata';
                    video.src = video.dataset.src;
                }
            } else if (video.hasAttribute('src')) {
                video.pause();
                video.removeAttribute('src');
                video.load();
            }

            if (key === currentKey) {
                video.muted = this.muted;
                video.play().catch(() => {});
            } else {
                video.pause();
            }
        });

        this.dwellTimer = setTimeout(() => this.onDwell(), 1000);
    },

    onDwell() {
        const challenge = this.challenges[this.active];
        if (!challenge) {
            return;
        }
        if (!challenge.viewed) {
            challenge.viewed = true;
            this.$wire.markViewed(challenge.id);
        }
        const slide = this.currentSlide(this.active);
        if (slide && !slide.is_original) {
            this.impressionQueue.add(slide.entry_id);
            if (!this.impressionTimer) {
                this.impressionTimer = setTimeout(() => this.flushImpressions(), 5000);
            }
        }
    },

    flushImpressions() {
        clearTimeout(this.impressionTimer);
        this.impressionTimer = null;
        if (this.impressionQueue.size === 0) {
            return;
        }
        const ids = [...this.impressionQueue];
        this.impressionQueue.clear();
        this.$wire.recordImpressions(ids);
    },

    toggleMute() {
        this.muted = !this.muted;
        this.$el.querySelectorAll('video[src]').forEach((video) => { video.muted = this.muted; });
    },

    async loadMore() {
        if (this.loading || this.exhausted) {
            return;
        }
        this.loading = true;
        const batch = await this.$wire.loadMore();
        if (!batch.length) {
            this.exhausted = true;
        }
        this.challenges.push(...batch.map(prepare));
        this.loading = false;
    },

    handle(result) {
        if (result?.redirect) {
            window.location.href = result.redirect;
            return false;
        }
        if (result?.error) {
            this.toast(result.error);
            return false;
        }
        return true;
    },

    toast(title) {
        window.dispatchEvent(new CustomEvent('versus-stake-toast', { detail: { title } }));
    },

    requireLogin() {
        if (guest) {
            window.location.href = loginUrl;
            return true;
        }
        return false;
    },

    async like(ci) {
        const slide = this.currentSlide(ci);
        const result = await this.$wire.toggleLike(slide.entry_id);
        if (this.handle(result)) {
            slide.liked = result.liked;
            slide.likes_count = result.likes_count;
        }
    },

    async vote(ci) {
        if (this.requireLogin()) {
            return;
        }
        const challenge = this.challenges[ci];
        const slide = this.currentSlide(ci);
        if (slide.is_mine || !challenge.is_open || challenge.my_vote_entry_id === slide.entry_id) {
            return;
        }
        if (challenge.my_vote_entry_id && !window.confirm(this.i18n.moveVote)) {
            return;
        }
        const result = await this.$wire.vote(slide.entry_id);
        if (this.handle(result)) {
            challenge.my_vote_entry_id = result.my_vote_entry_id;
            challenge.slides.forEach((s) => {
                if (result.counts[s.entry_id] !== undefined) {
                    s.votes_count = result.counts[s.entry_id];
                }
            });
        }
    },

    async accept(ci) {
        if (this.requireLogin()) {
            return;
        }
        const challenge = this.challenges[ci];
        if (challenge.my_entry_id) {
            this.goToSlide(ci, challenge.slides.findIndex((s) => s.entry_id === challenge.my_entry_id));
            return;
        }
        const result = await this.$wire.accept(challenge.id);
        if (this.handle(result)) {
            window.location.href = result.respond_url;
        }
    },

    goToSlide(ci, si) {
        if (si < 0) {
            return;
        }
        const carousel = this.$el.querySelector(`[data-carousel="${ci}"]`);
        carousel?.scrollTo({ left: carousel.clientWidth * si, behavior: 'smooth' });
    },

    async openComments(ci) {
        this.sheetEntry = this.currentSlide(ci);
        this.comments = [];
        this.sheet = 'comments';
        this.comments = await this.$wire.comments(this.sheetEntry.entry_id);
    },

    async postComment() {
        const body = this.commentBody.trim();
        if (!body || this.requireLogin()) {
            return;
        }
        const result = await this.$wire.postComment(this.sheetEntry.entry_id, body);
        if (this.handle(result)) {
            this.comments.unshift(result.comment);
            this.sheetEntry.comments_count++;
            this.commentBody = '';
        }
    },

    async openAll(ci) {
        this.allResponses = [];
        this.sheet = 'all';
        this.allResponses = await this.$wire.allResponses(this.challenges[ci].id);
    },

    async share(ci) {
        const slide = this.currentSlide(ci);
        if (navigator.share) {
            try {
                await navigator.share({ title: this.challenges[ci].title, url: slide.share_url });
            } catch (e) {
                // User dismissed the share sheet.
            }
            return;
        }
        await this.copy(slide.share_url);
    },

    async copy(url) {
        try {
            await navigator.clipboard.writeText(url);
            this.toast(this.i18n.linkCopied);
        } catch (e) {
            // Clipboard unavailable (insecure context).
        }
    },

    highlightTags(text) {
        return escapeHtml(text).replace(/#([\p{L}\p{N}_]+)/gu, '<span class="text-violet-400">#$1</span>');
    },

    dismissHint() {
        if (!this.hint) {
            return;
        }
        this.hint = false;
        if (guest) {
            try { localStorage.setItem(HINT_KEY, '1'); } catch (e) { /* storage blocked */ }
        } else {
            this.$wire.dismissSwipeHint();
        }
    },
});
```

In `resources/js/app.js`: add `import challengeFeed from './challenges/feed';` under `import './bootstrap';`, and inside the existing `document.addEventListener('alpine:init', () => {` block add as the first statement:

```js
    window.Alpine.data('challengeFeed', challengeFeed);
```

- [ ] **Step 10: Run tests**

Run: `php artisan test --filter="ChallengeFeedPresenterTest|ChallengeFeedPageTest"` → PASS.

- [ ] **Step 11: Manual check in the browser**

Run `make npm CMD="run build"`, seed two challenges by hand in tinker with ready entries pointing at any small mp4 copied to `storage/app/public/videos/`, open `http://versus.local/challenges` at 375×812: vertical swipe changes challenge, horizontal swipe switches pill `Challenge` → `Response 1/N`, only the visible video plays, vote/like update without a reload, the hint disappears after the first horizontal swipe.

- [ ] **Step 12: Commit**

```bash
git add app/Services/Challenges/ChallengeFeedPresenter.php app/Livewire/ChallengeFeed.php resources/views/livewire/challenge-feed.blade.php resources/js/challenges/feed.js resources/js/app.js routes/web.php tests/Feature/Challenges/ChallengeFeedPresenterTest.php tests/Feature/Challenges/ChallengeFeedPageTest.php
git commit -m "feat(challenges): vertical feed with responses carousel

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 15: Create / respond form

**Files:**
- Create: `app/Livewire/ChallengeForm.php`, `resources/views/livewire/challenge-form.blade.php`
- Create: `resources/js/challenges/upload.js`; Modify: `resources/js/app.js`
- Modify: `routes/web.php` (replace the two temporary closures from Task 14)
- Test: `tests/Feature/Challenges/ChallengeFormTest.php`

**Interfaces:**
- Consumes: `CreateChallengeAction`, `SubmitResponseAction`, upload routes (Task 3).
- Produces:
  - `ChallengeForm` mounted by `challenges.create` (optional query `retry={slug}` for the user's failed challenge) and `challenges.respond` (`{challenge:slug}`); public `uploadId`, `title`, `rules`, `duration`, `username`; method `publish()` redirects to `challenges.mine` with session flash `challenge_status`.
  - Alpine component `challengeUpload({ maxSeconds, maxBytes, urls, i18n })` that sets `$wire.uploadId`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Challenges;

use App\Livewire\ChallengeForm;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\User;
use App\Services\Video\UploadStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ChallengeFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
    }

    private function upload(User $user): string
    {
        $store = app(UploadStore::class);
        $id = $store->start($user);
        $store->appendChunk($user, $id, 0, UploadedFile::fake()->createWithContent('c', 'bytes'));
        $store->complete($user, $id);

        return $id;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('challenges.create'))->assertRedirect(route('login'));
    }

    public function test_publishing_a_new_challenge(): void
    {
        $user = User::factory()->create(['username' => 'dan']);

        Livewire::actingAs($user)
            ->test(ChallengeForm::class)
            ->set('uploadId', $this->upload($user))
            ->set('title', 'Kickflip clean')
            ->set('rules', 'Land it')
            ->set('duration', Challenge::DURATION_24H)
            ->call('publish')
            ->assertHasNoErrors()
            ->assertRedirect(route('challenges.mine'));

        $this->assertSame('Kickflip clean', Challenge::sole()->title);
    }

    public function test_missing_video_and_fields_show_errors(): void
    {
        $user = User::factory()->create(['username' => 'dan']);

        Livewire::actingAs($user)->test(ChallengeForm::class)
            ->call('publish')
            ->assertHasErrors(['uploadId']);

        Livewire::actingAs($user)->test(ChallengeForm::class)
            ->set('uploadId', $this->upload($user))
            ->call('publish')
            ->assertHasErrors(['title', 'rules', 'duration']);
    }

    public function test_user_without_username_sees_the_field(): void
    {
        $user = User::factory()->create(['username' => null]);

        Livewire::actingAs($user)->test(ChallengeForm::class)
            ->assertSee(__('challenges.field_username'));
    }

    public function test_respond_mode_prefills_locked_fields_and_publishes_response(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create(['title' => 'Play this riff', 'rules' => 'Clean']);
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ChallengeForm::class, ['challenge' => $challenge])
            ->assertSet('title', 'Play this riff')
            ->assertSee(__('challenges.publish_response'))
            ->set('title', 'hacked')
            ->set('uploadId', $this->upload($user))
            ->call('publish')
            ->assertRedirect(route('challenges.mine'));

        $this->assertSame('Play this riff', $challenge->fresh()->title);
        $this->assertTrue(ChallengeEntry::where('challenge_id', $challenge->id)->where('user_id', $user->id)->exists());
    }

    public function test_respond_to_closed_challenge_shows_error(): void
    {
        $challenge = Challenge::factory()->withOriginal()->create(['ends_at' => now()->subMinute()]);
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ChallengeForm::class, ['challenge' => $challenge])
            ->set('uploadId', $this->upload($user))
            ->call('publish')
            ->assertHasErrors(['challenge']);
    }

    public function test_retry_prefills_from_failed_challenge_and_replaces_it(): void
    {
        $user = User::factory()->create(['username' => 'dan']);
        $failed = Challenge::factory()->for($user)->failed()->create(['title' => 'Try again', 'rules' => 'Rules', 'duration' => Challenge::DURATION_7D]);

        Livewire::withQueryParams(['retry' => $failed->slug])
            ->actingAs($user)
            ->test(ChallengeForm::class)
            ->assertSet('title', 'Try again')
            ->assertSet('duration', Challenge::DURATION_7D)
            ->set('uploadId', $this->upload($user))
            ->call('publish')
            ->assertHasNoErrors();

        $this->assertNull(Challenge::find($failed->id));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ChallengeFormTest`
Expected: FAIL with `Class "App\Livewire\ChallengeForm" not found`.

- [ ] **Step 3: Component**

`app/Livewire/ChallengeForm.php`:

```php
<?php

namespace App\Livewire;

use App\Actions\Challenges\CreateChallengeAction;
use App\Actions\Challenges\SubmitResponseAction;
use App\Models\Challenge;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class ChallengeForm extends Component
{
    #[Locked]
    public ?int $challengeId = null;

    #[Locked]
    public ?int $retryChallengeId = null;

    public string $uploadId = '';

    public string $title = '';

    public string $rules = '';

    public string $duration = '';

    public string $username = '';

    public function mount(?Challenge $challenge = null): void
    {
        if ($challenge !== null && $challenge->exists) {
            $this->challengeId = $challenge->id;
            $this->title = $challenge->title;
            $this->rules = $challenge->rules;

            return;
        }

        $retry = request()->query('retry');
        if (is_string($retry)) {
            $failed = Challenge::query()
                ->where('slug', $retry)
                ->where('user_id', Auth::id())
                ->where('status', Challenge::STATUS_FAILED)
                ->first();

            if ($failed !== null) {
                $this->retryChallengeId = $failed->id;
                $this->title = $failed->title;
                $this->rules = $failed->rules;
                $this->duration = $failed->duration;
            }
        }
    }

    public function publish(CreateChallengeAction $create, SubmitResponseAction $respond): void
    {
        /** @var User $user */
        $user = Auth::user();

        if ($this->uploadId === '') {
            $this->addError('uploadId', __('challenges.video_required'));

            return;
        }

        try {
            if ($this->challengeId !== null) {
                $respond($user, Challenge::findOrFail($this->challengeId), $this->uploadId);
            } else {
                $create(
                    $user,
                    $this->uploadId,
                    $this->title,
                    $this->rules,
                    $this->duration,
                    $user->username === null ? $this->username : null,
                    $this->retryChallengeId !== null ? Challenge::find($this->retryChallengeId) : null,
                );
            }
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, (string) $messages[0]);
            }
            if (isset($e->errors()['upload']) || isset($e->errors()['challenge'])) {
                $this->uploadId = ''; // The upload was consumed or discarded; force a re-upload.
            }

            return;
        }

        session()->flash('challenge_status', __('challenges.processing_toast'));
        $this->redirectRoute('challenges.mine');
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $challenge = $this->challengeId !== null ? Challenge::find($this->challengeId) : null;

        return view('livewire.challenge-form', [
            'challenge' => $challenge,
            'isResponse' => $challenge !== null,
            'needsUsername' => $challenge === null && $user->username === null,
            'durations' => array_keys((array) config('versus.challenges.durations')),
            'maxSeconds' => (int) config('versus.challenges.max_video_seconds'),
            'maxBytes' => (int) config('versus.challenges.max_upload_mb') * 1024 * 1024,
        ]);
    }
}
```

- [ ] **Step 4: View**

`resources/views/livewire/challenge-form.blade.php`:

```blade
<div x-data="challengeUpload({
        maxSeconds: @js($maxSeconds),
        maxBytes: @js($maxBytes),
        urls: @js([
            'start' => route('challenge-uploads.start'),
            'chunk' => route('challenge-uploads.chunk', '__ID__'),
            'complete' => route('challenge-uploads.complete', '__ID__'),
        ]),
        backUrl: @js(url()->previous() !== url()->current() ? url()->previous() : route('challenges.index')),
        i18n: @js([
            'tooLong' => __('challenges.video_too_long', ['seconds' => $maxSeconds]),
            'tooBig' => __('challenges.video_too_big', ['mb' => config('versus.challenges.max_upload_mb')]),
            'failed' => __('challenges.upload_failed'),
        ]),
     })"
     @input="dirty = true"
     class="min-h-screen bg-navy-900 px-4 pb-10 pt-4 text-white">

    <header class="mb-6 flex items-center gap-4">
        <button type="button" class="p-2 text-2xl leading-none" @click="back()" aria-label="{{ __('challenges.leave') }}">‹</button>
        <h1 class="text-2xl font-bold">{{ $isResponse ? __('challenges.respond_title') : __('challenges.create_title') }}</h1>
    </header>

    {{-- Video --}}
    <div class="overflow-hidden rounded-2xl border-2 border-dashed border-indigo-500/70">
        <div class="relative flex aspect-[9/12] items-center justify-center bg-white/5" @click="pick()">
            <video x-show="previewUrl" :src="previewUrl" class="absolute inset-0 h-full w-full object-contain" playsinline muted loop autoplay></video>
            <div x-show="!previewUrl" class="text-center text-white/70">
                <div class="mb-3 text-4xl text-indigo-400">🎬</div>
                <p>{{ __('challenges.upload_hint') }}</p>
            </div>
            <div x-show="state === 'uploading'" class="absolute inset-x-0 bottom-0 bg-black/70 px-4 py-2 text-sm"
                 x-text="@js(__('challenges.uploading', ['percent' => '__P__'])).replace('__P__', progress)"></div>
        </div>
        <button type="button" class="flex w-full items-center justify-center gap-2 border-t border-dashed border-indigo-500/70 py-3 font-semibold" @click="pick()">
            ⬆ {{ __('challenges.upload') }}
        </button>
        <input x-ref="file" type="file" accept="video/*" class="hidden" @change="onFile($event)">
    </div>
    <p x-show="error" x-text="error" class="mt-2 text-sm text-rose-400"></p>
    @error('uploadId') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror
    @error('upload') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror
    @error('challenge') <p class="mt-2 text-sm text-rose-400">{{ $message }}</p> @enderror

    {{-- Fields --}}
    <div class="mt-6 space-y-5">
        <label class="block">
            <span class="mb-2 block">{{ __('challenges.field_title') }} *</span>
            <input type="text" maxlength="100" wire:model="title" @disabled($isResponse)
                   placeholder="{{ __('challenges.field_title_placeholder') }}"
                   class="w-full rounded-xl border-white/10 bg-white/5 text-white disabled:opacity-70">
            <span class="mt-1 block text-right text-xs text-white/50" x-text="($wire.title || '').length + '/100'"></span>
            @error('title') <span class="text-sm text-rose-400">{{ $message }}</span> @enderror
        </label>

        <label class="block">
            <span class="mb-2 block">{{ __('challenges.field_rules') }} *</span>
            <textarea rows="4" maxlength="500" wire:model="rules" @disabled($isResponse)
                      placeholder="{{ __('challenges.field_rules_placeholder') }}"
                      class="w-full rounded-xl border-white/10 bg-white/5 text-white disabled:opacity-70"></textarea>
            <span class="mt-1 block text-right text-xs text-white/50" x-text="($wire.rules || '').length + '/500'"></span>
            @error('rules') <span class="text-sm text-rose-400">{{ $message }}</span> @enderror
        </label>

        @if ($isResponse)
            <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                <span class="text-white/60">{{ __('challenges.time_left') }}:</span>
                <span x-data="countdown(@js($challenge->ends_at?->toIso8601String()))" x-init="start()" x-text="label" class="font-semibold"></span>
            </div>
        @else
            <div x-data="{ open: false }">
                <span class="mb-2 block">{{ __('challenges.field_deadline') }}</span>
                <button type="button" @click="open = true" class="flex w-full items-center justify-between rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                    <span>🕒 {{ $duration ? __('challenges.duration_'.$duration) : __('challenges.select_time') }}</span>
                    <span>›</span>
                </button>
                @error('duration') <span class="text-sm text-rose-400">{{ $message }}</span> @enderror
                <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end bg-black/60" @click.self="open = false">
                    <div class="w-full space-y-2 rounded-t-3xl bg-navy-900 p-5">
                        @foreach ($durations as $option)
                            <button type="button" class="w-full rounded-xl px-4 py-3 text-left {{ $duration === $option ? 'bg-indigo-600' : 'bg-white/5' }}"
                                    wire:click="$set('duration', '{{ $option }}')" @click="open = false; dirty = true">
                                {{ __('challenges.duration_'.$option) }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        @if ($needsUsername)
            <label class="block">
                <span class="mb-2 block">{{ __('challenges.field_username') }}</span>
                <input type="text" maxlength="32" wire:model="username" class="w-full rounded-xl border-white/10 bg-white/5 text-white" placeholder="@username">
                @error('username') <span class="text-sm text-rose-400">{{ $message }}</span> @enderror
            </label>
        @endif
        @error('daily_limit') <p class="text-sm text-rose-400">{{ $message }}</p> @enderror
    </div>

    <button type="button"
            class="mt-8 flex h-14 w-full items-center justify-center rounded-2xl bg-indigo-600 font-semibold uppercase tracking-wide disabled:opacity-50"
            :disabled="state === 'uploading'"
            @click="submitting = true; $wire.publish().finally(() => { submitting = false })">
        {{ $isResponse ? __('challenges.publish_response') : __('challenges.publish') }}
    </button>

    {{-- Leave confirmation --}}
    <div x-show="confirmLeave" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-6">
        <div class="w-full max-w-sm space-y-4 rounded-2xl bg-navy-900 p-6 text-center">
            <p class="text-lg font-semibold">{{ __('challenges.leave_confirm') }}</p>
            <div class="flex gap-3">
                <button type="button" class="flex-1 rounded-xl bg-white/10 py-3" @click="confirmLeave = false">{{ __('challenges.stay') }}</button>
                <button type="button" class="flex-1 rounded-xl bg-rose-600 py-3" @click="leave()">{{ __('challenges.leave') }}</button>
            </div>
        </div>
    </div>
</div>
```

- [ ] **Step 5: Alpine upload component**

`resources/js/challenges/upload.js`:

```js
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

async function post(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        credentials: 'same-origin',
        body,
    });
    const json = await response.json().catch(() => ({}));
    if (!response.ok) {
        const message = json.errors ? Object.values(json.errors)[0][0] : json.message;
        const error = new Error(message || '');
        error.status = response.status;
        throw error;
    }
    return json;
}

function postChunk(url, index, blob) {
    const form = new FormData();
    form.append('index', String(index));
    form.append('chunk', blob, 'chunk');
    return post(url, form);
}

// Retries network errors and 5xx/429; a 4xx is a real rejection and surfaces immediately.
async function withRetry(fn, attempts = 3) {
    for (let attempt = 1; ; attempt++) {
        try {
            return await fn();
        } catch (error) {
            const retriable = !error.status || error.status >= 500 || error.status === 429;
            if (!retriable || attempt >= attempts) {
                throw error;
            }
            await new Promise((resolve) => setTimeout(resolve, 1000 * attempt));
        }
    }
}

function readDuration(file) {
    return new Promise((resolve) => {
        const video = document.createElement('video');
        const url = URL.createObjectURL(file);
        video.preload = 'metadata';
        video.onloadedmetadata = () => {
            URL.revokeObjectURL(url);
            resolve(Number.isFinite(video.duration) ? video.duration : null);
        };
        video.onerror = () => {
            URL.revokeObjectURL(url);
            resolve(null);
        };
        video.src = url;
    });
}

export default ({ maxSeconds, maxBytes, urls, backUrl, i18n }) => ({
    state: 'idle',
    progress: 0,
    previewUrl: null,
    error: '',
    dirty: false,
    submitting: false,
    confirmLeave: false,

    init() {
        window.addEventListener('beforeunload', (event) => {
            if (this.dirty && !this.submitting) {
                event.preventDefault();
                event.returnValue = '';
            }
        });
    },

    pick() {
        if (this.state !== 'uploading') {
            this.$refs.file.click();
        }
    },

    back() {
        if (this.dirty) {
            this.confirmLeave = true;
            return;
        }
        window.location.href = backUrl;
    },

    leave() {
        this.dirty = false;
        window.location.href = backUrl;
    },

    async onFile(event) {
        const file = event.target.files[0];
        event.target.value = '';
        if (!file) {
            return;
        }
        this.error = '';
        if (file.size > maxBytes) {
            this.error = i18n.tooBig;
            return;
        }
        const duration = await readDuration(file);
        if (duration === null) {
            this.error = i18n.failed;
            return;
        }
        if (duration > maxSeconds + 0.5) {
            this.error = i18n.tooLong;
            return;
        }

        if (this.previewUrl) {
            URL.revokeObjectURL(this.previewUrl);
        }
        this.previewUrl = URL.createObjectURL(file);
        this.dirty = true;
        await this.upload(file);
    },

    async upload(file) {
        this.state = 'uploading';
        this.progress = 0;
        this.$wire.set('uploadId', '', false);

        try {
            const { upload_id: id, chunk_bytes: size } = await withRetry(() => post(urls.start));
            const total = Math.max(1, Math.ceil(file.size / size));
            for (let index = 0; index < total; index++) {
                const blob = file.slice(index * size, (index + 1) * size);
                await withRetry(() => postChunk(urls.chunk.replace('__ID__', id), index, blob));
                this.progress = Math.round(((index + 1) / total) * 100);
            }
            await withRetry(() => post(urls.complete.replace('__ID__', id)));
            this.$wire.set('uploadId', id, false);
            this.state = 'done';
        } catch (error) {
            this.state = 'idle';
            this.error = error.message || i18n.failed;
        }
    },
});
```

In `resources/js/app.js`: add `import challengeUpload from './challenges/upload';` next to the feed import, and inside `alpine:init`:

```js
    window.Alpine.data('challengeUpload', challengeUpload);
```

- [ ] **Step 6: Routes**

In `routes/web.php` import `use App\Livewire\ChallengeForm;` and replace the two temporary lines from Task 14:

```php
Route::middleware(['auth', 'verified'])->get('/challenges/create', ChallengeForm::class)->name('challenges.create');
Route::middleware(['auth', 'verified'])->get('/c/{challenge:slug}/respond', ChallengeForm::class)->name('challenges.respond');
```

- [ ] **Step 7: Run tests**

Run: `php artisan test --filter=ChallengeFormTest` → PASS. (The redirect assertion only compares URLs; the `/my-challenges` closure is not executed.)

- [ ] **Step 8: Manual check**

`make npm CMD="run build"`, then at 375×812 open `/challenges/create`: pick a 70 s video → "Video must be up to 60 s" and nothing uploads; pick a 20 s video → preview plays, progress reaches 100 %; "‹" shows the leave dialog; publish with empty title shows the field error and keeps the uploaded video; valid publish lands on `/my-challenges`. With `make art CMD=queue:work` running the challenge becomes active.

- [ ] **Step 9: Commit**

```bash
git add app/Livewire/ChallengeForm.php resources/views/livewire/challenge-form.blade.php resources/js/challenges/upload.js resources/js/app.js routes/web.php tests/Feature/Challenges/ChallengeFormTest.php
git commit -m "feat(challenges): create and respond form with chunked upload

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 16: My Challenges page

**Files:**
- Create: `app/Services/Challenges/MyChallengesQuery.php`
- Create: `app/Livewire/MyChallenges.php`, `resources/views/livewire/my-challenges.blade.php`
- Modify: `routes/web.php` (replace the temporary `/my-challenges` closure)
- Test: `tests/Feature/Challenges/MyChallengesTest.php`

**Interfaces:**
- Consumes: `LeaveChallengeAction`, routes `challenges.show|respond|create`.
- Produces:
  - `MyChallengesQuery::cards(User $user, int $limit): list<array>` — each card `challenge` (model), `state` (one of `accepted_open|accepted_expired|response_processing|response_ready|own|own_failed|closed`), `has_entry` (bool), `my_entry_id` (?int), `winner_name` (?string), `winner_entry_id` (?int). Order: active by `ends_at` asc → processing/failed → closed by `closed_at` desc.
  - `MyChallengesQuery::activeCount(User $user): int`
  - `MyChallenges` Livewire: `loadMore()`, `leave(int $challengeId)`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Challenges;

use App\Livewire\MyChallenges;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeParticipant;
use App\Models\User;
use App\Services\Challenges\MyChallengesQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MyChallengesTest extends TestCase
{
    use RefreshDatabase;

    private function accept(User $user, Challenge $challenge): void
    {
        ChallengeParticipant::create(['user_id' => $user->id, 'challenge_id' => $challenge->id, 'accepted_at' => now()]);
    }

    public function test_lists_own_and_accepted_in_status_order_with_states(): void
    {
        $user = User::factory()->create();

        $closed = Challenge::factory()->closed()->withOriginal()->create();
        $this->accept($user, $closed);
        $ownFailed = Challenge::factory()->for($user)->failed()->create();
        $later = Challenge::factory()->withOriginal()->create(['ends_at' => now()->addDays(2)]);
        $this->accept($user, $later);
        $sooner = Challenge::factory()->withOriginal()->create(['ends_at' => now()->addHour()]);
        $this->accept($user, $sooner);
        ChallengeEntry::factory()->for($sooner)->for($user)->processing()->create();
        $own = Challenge::factory()->for($user)->withOriginal()->create(['ends_at' => now()->addDays(5)]);
        Challenge::factory()->withOriginal()->create(); // unrelated

        $cards = collect(app(MyChallengesQuery::class)->cards($user, 20));

        $this->assertSame(
            [$sooner->id, $later->id, $own->id, $ownFailed->id, $closed->id],
            $cards->pluck('challenge.id')->all(),
        );
        $this->assertSame(
            ['response_processing', 'accepted_open', 'own', 'own_failed', 'closed'],
            $cards->pluck('state')->all(),
        );
        $this->assertTrue($cards[0]['has_entry']);
        $this->assertSame(3, app(MyChallengesQuery::class)->activeCount($user));
    }

    public function test_page_renders_cards_buttons_and_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('challenges.mine'))->assertOk()->assertSee(__('challenges.my_empty'));

        $challenge = Challenge::factory()->withOriginal()->create(['title' => 'Skate Trick Challenge']);
        $this->accept($user, $challenge);

        $this->actingAs($user)->get(route('challenges.mine'))
            ->assertOk()
            ->assertSee('Skate Trick Challenge')
            ->assertSee(__('challenges.upload_response'))
            ->assertSee(route('challenges.respond', $challenge->slug), false);
    }

    public function test_closed_card_shows_winner_and_results_link(): void
    {
        $user = User::factory()->create();
        $challenge = Challenge::factory()->closed()->withOriginal()->create();
        $winner = ChallengeEntry::factory()->for($challenge)->for($user)->create();
        $this->accept($user, $challenge); // SubmitResponseAction always records participation.
        $challenge->update(['winner_entry_id' => $winner->id]);

        $this->actingAs($user)->get(route('challenges.mine'))
            ->assertSee(__('challenges.winner_short', ['name' => $user->name]))
            ->assertSee(route('challenges.show', ['challenge' => $challenge->slug, 'entry' => $winner->id]), false)
            ->assertDontSee(__('challenges.upload_response'));
    }

    public function test_leave_removes_accepted_challenge(): void
    {
        $user = User::factory()->create();
        $challenge = Challenge::factory()->withOriginal()->create();
        $this->accept($user, $challenge);

        Livewire::actingAs($user)->test(MyChallenges::class)->call('leave', $challenge->id);

        $this->assertSame(0, ChallengeParticipant::count());
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('challenges.mine'))->assertRedirect(route('login'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MyChallengesTest`
Expected: FAIL with `Class "App\Services\Challenges\MyChallengesQuery" not found`.

- [ ] **Step 3: Query service**

`app/Services/Challenges/MyChallengesQuery.php`:

```php
<?php

namespace App\Services\Challenges;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class MyChallengesQuery
{
    /** @return list<array{challenge: Challenge, state: string, has_entry: bool, my_entry_id: int|null, winner_name: string|null, winner_entry_id: int|null}> */
    public function cards(User $user, int $limit): array
    {
        $challenges = $this->base($user)
            ->with(['user:id,name,username', 'original', 'winnerEntry.user:id,name'])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'processing' THEN 1 WHEN 'failed' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE WHEN status = 'active' THEN ends_at END")
            ->orderByDesc('closed_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $entries = ChallengeEntry::query()
            ->where('user_id', $user->id)
            ->whereIn('challenge_id', $challenges->modelKeys())
            ->get()
            ->keyBy('challenge_id');

        return $challenges->map(function (Challenge $challenge) use ($user, $entries): array {
            /** @var ChallengeEntry|null $entry */
            $entry = $entries->get($challenge->id);
            $isOwn = $challenge->user_id === $user->id;

            $state = match (true) {
                $challenge->status === Challenge::STATUS_CLOSED => 'closed',
                $isOwn && $challenge->status === Challenge::STATUS_FAILED => 'own_failed',
                $isOwn => 'own',
                $entry === null => $challenge->isOpen() ? 'accepted_open' : 'accepted_expired',
                $entry->status === ChallengeEntry::STATUS_PROCESSING => 'response_processing',
                default => 'response_ready',
            };

            return [
                'challenge' => $challenge,
                'state' => $state,
                'has_entry' => ! $isOwn && $entry !== null,
                'my_entry_id' => $entry?->id,
                'winner_name' => $challenge->winnerEntry?->user?->name,
                'winner_entry_id' => $challenge->winner_entry_id,
            ];
        })->values()->all();
    }

    public function activeCount(User $user): int
    {
        return $this->base($user)->where('status', Challenge::STATUS_ACTIVE)->count();
    }

    /** @return Builder<Challenge> */
    private function base(User $user): Builder
    {
        return Challenge::query()->where(function (Builder $query) use ($user): void {
            $query->where('user_id', $user->id)
                ->orWhereIn('id', ChallengeParticipant::select('challenge_id')->where('user_id', $user->id));
        });
    }
}
```

> The own-challenge card's "Responses (N)" reads `challenge.entries_count`; its original is not a "response", so `has_entry` stays false for own cards (no green check).

- [ ] **Step 4: Component and view**

`app/Livewire/MyChallenges.php`:

```php
<?php

namespace App\Livewire;

use App\Actions\Challenges\LeaveChallengeAction;
use App\Models\Challenge;
use App\Models\User;
use App\Services\Challenges\MyChallengesQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class MyChallenges extends Component
{
    private const PER_PAGE = 20;

    public int $pages = 1;

    public function loadMore(): void
    {
        $this->pages++;
    }

    public function leave(int $challengeId, LeaveChallengeAction $leave): void
    {
        try {
            $leave($this->user(), Challenge::findOrFail($challengeId));
        } catch (ValidationException $e) {
            $this->dispatch('versus-stake-toast', title: (string) collect($e->errors())->flatten()->first());
        }
    }

    public function render(MyChallengesQuery $query): View
    {
        $limit = $this->pages * self::PER_PAGE;
        $cards = $query->cards($this->user(), $limit + 1);

        return view('livewire.my-challenges', [
            'cards' => array_slice($cards, 0, $limit),
            'hasMore' => count($cards) > $limit,
            'activeCount' => $query->activeCount($this->user()),
        ]);
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
```

`resources/views/livewire/my-challenges.blade.php`:

```blade
<div class="min-h-screen bg-navy-900 px-4 pb-8 pt-6 text-white"
     @if (session('challenge_status'))
         x-init="$nextTick(() => window.dispatchEvent(new CustomEvent('versus-stake-toast', { detail: { title: @js(session('challenge_status')) } })))"
     @endif>
    <header class="mb-6 flex items-baseline justify-between">
        <h1 class="text-3xl font-bold">{{ __('challenges.my_title') }}</h1>
        <span class="text-white/60">{{ __('challenges.active_count', ['count' => $activeCount]) }}</span>
    </header>

    @if ($cards === [])
        <div class="mt-20 space-y-4 text-center text-white/70">
            <p>{{ __('challenges.my_empty') }}</p>
            <a href="{{ route('challenges.index') }}" class="inline-block rounded-full bg-indigo-600 px-5 py-2 font-semibold text-white">{{ __('challenges.go_to_challenges') }}</a>
        </div>
    @endif

    <div class="space-y-4">
        @foreach ($cards as $card)
            @php
                /** @var \App\Models\Challenge $challenge */
                $challenge = $card['challenge'];
                $pill = match ($challenge->status) {
                    \App\Models\Challenge::STATUS_ACTIVE => ['challenges.status_active', 'bg-teal-500/15 text-teal-300'],
                    \App\Models\Challenge::STATUS_PROCESSING => ['challenges.status_processing', 'bg-white/10 text-white/60'],
                    \App\Models\Challenge::STATUS_FAILED => ['challenges.status_failed', 'bg-rose-500/15 text-rose-300'],
                    default => ['challenges.status_closed', 'bg-violet-500/20 text-violet-300'],
                };
                $showUrl = route('challenges.show', $challenge->slug);
            @endphp
            <article wire:key="card-{{ $challenge->id }}" class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                <div class="flex gap-4">
                    <a href="{{ $showUrl }}" class="relative h-36 w-28 shrink-0 overflow-hidden rounded-xl bg-white/5">
                        @if ($challenge->original?->posterUrl())
                            <img src="{{ $challenge->original->posterUrl() }}" alt="" class="h-full w-full object-cover">
                        @endif
                        @if ($card['has_entry'])
                            <span class="absolute inset-0 flex items-center justify-center">
                                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500/70 text-xl">✓</span>
                            </span>
                        @endif
                    </a>
                    <a href="{{ $showUrl }}" class="min-w-0 flex-1">
                        <h2 class="text-lg font-semibold leading-tight">{{ $challenge->title }}</h2>
                        <p class="mt-1 text-white/60">{{ __('challenges.by', ['name' => '@'.($challenge->user->username ?? $challenge->user->name)]) }}</p>
                        <p class="mt-2 line-clamp-3 text-sm text-white/70">{{ $challenge->rules }}</p>
                    </a>
                </div>

                <div class="mt-3 flex items-center justify-between">
                    @if ($card['state'] === 'closed')
                        <span class="text-sm text-white/70">
                            {{ $card['winner_name'] ? __('challenges.winner_short', ['name' => $card['winner_name']]) : __('challenges.no_votes') }}
                        </span>
                    @elseif ($challenge->ends_at)
                        <span class="text-sm text-violet-300">🕒 <span x-data="countdown(@js($challenge->ends_at->toIso8601String()))" x-init="start()" x-text="label"></span></span>
                    @else
                        <span></span>
                    @endif
                    <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $pill[1] }}">{{ __($pill[0]) }}</span>
                </div>

                <div class="mt-3 flex gap-3">
                    @switch($card['state'])
                        @case('accepted_open')
                            <a href="{{ route('challenges.respond', $challenge->slug) }}" class="flex h-12 flex-1 items-center justify-center rounded-xl bg-indigo-600 text-sm font-semibold uppercase">⬆ {{ __('challenges.upload_response') }}</a>
                            <button type="button" wire:click="leave({{ $challenge->id }})" wire:confirm="{{ __('challenges.remove_from_mine') }}?" class="h-12 rounded-xl border border-white/15 px-4" aria-label="{{ __('challenges.remove_from_mine') }}">•••</button>
                            @break
                        @case('response_processing')
                            <span class="flex h-12 flex-1 items-center justify-center rounded-xl bg-white/10 text-sm text-white/60">{{ __('challenges.response_processing') }}</span>
                            @break
                        @case('response_ready')
                            <a href="{{ route('challenges.show', ['challenge' => $challenge->slug, 'entry' => $card['my_entry_id']]) }}" class="flex h-12 flex-1 items-center justify-center rounded-xl border border-white/15 text-sm font-semibold uppercase">{{ __('challenges.my_response') }}</a>
                            @break
                        @case('own')
                            <a href="{{ $showUrl }}" class="flex h-12 flex-1 items-center justify-center rounded-xl border border-white/15 text-sm font-semibold uppercase">{{ __('challenges.responses_count', ['count' => $challenge->entries_count]) }}</a>
                            @break
                        @case('own_failed')
                            <a href="{{ route('challenges.create', ['retry' => $challenge->slug]) }}" class="flex h-12 flex-1 items-center justify-center rounded-xl bg-rose-600 text-sm font-semibold uppercase">{{ __('challenges.upload_again') }}</a>
                            @break
                        @case('closed')
                            <a href="{{ $card['winner_entry_id'] ? route('challenges.show', ['challenge' => $challenge->slug, 'entry' => $card['winner_entry_id']]) : $showUrl }}" class="flex h-12 flex-1 items-center justify-center rounded-xl border border-white/15 text-sm font-semibold uppercase">{{ __('challenges.results') }}</a>
                            @break
                    @endswitch
                </div>
            </article>
        @endforeach
    </div>

    @if ($hasMore)
        <button type="button" wire:click="loadMore" class="mt-6 w-full rounded-xl border border-white/15 py-3">{{ __('challenges.load_more') }}</button>
    @endif
</div>
```

- [ ] **Step 5: Route**

In `routes/web.php` import `use App\Livewire\MyChallenges;` and replace the temporary line inside the auth group with:

```php
    Route::get('/my-challenges', MyChallenges::class)->name('challenges.mine');
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --filter="MyChallengesTest|ChallengeFormTest"` → PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services/Challenges/MyChallengesQuery.php app/Livewire/MyChallenges.php resources/views/livewire/my-challenges.blade.php routes/web.php tests/Feature/Challenges/MyChallengesTest.php
git commit -m "feat(challenges): my challenges page

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 17: Battles flag in routes and navigation

**Files:**
- Create: `app/Http/Middleware/EnsureBattlesEnabled.php`
- Modify: `bootstrap/app.php` (alias), `routes/web.php`
- Create: `resources/views/components/icon/bolt.blade.php`, `resources/views/components/icon/swords.blade.php`
- Modify: `resources/views/layouts/bottom-nav.blade.php`, `resources/views/layouts/navigation.blade.php`, `resources/views/layouts/app.blade.php`
- Test: `tests/Feature/Challenges/ChallengesNavigationTest.php`

**Interfaces:**
- Produces: middleware alias `battles` — when `versus.battles_enabled` is false, any GET to a battles-only page redirects to `challenges.index`. Bottom nav with the flag off: Home (`home`) · Challenges (`challenges.index`) · ＋ (`challenges.create`) · My Challenges (`challenges.mine`, auth) · Profile. Bottom nav hidden on `challenges.create` and `challenges.respond`; top header hidden on `challenges.index` and `challenges.show` (the feed draws its own bell).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Challenges;

use App\Models\Battle;
use App\Models\Challenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChallengesNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['versus.battles_enabled' => false]);
    }

    public function test_battle_pages_redirect_to_challenges(): void
    {
        $user = User::factory()->create();
        $battle = Battle::factory()->create();

        $this->get('/')->assertRedirect(route('challenges.index'));
        $this->get(route('battles.show', $battle->slug))->assertRedirect(route('challenges.index'));
        $this->get(route('leaderboard'))->assertRedirect(route('challenges.index'));
        $this->actingAs($user)->get(route('wallet'))->assertRedirect(route('challenges.index'));
        $this->actingAs($user)->get(route('battles.create'))->assertRedirect(route('challenges.index'));
    }

    public function test_bottom_nav_shows_challenge_tabs(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('challenges.mine'))->assertOk()->getContent();

        $this->assertStringContainsString(__('challenges.nav_challenges'), $html);
        $this->assertStringContainsString(__('challenges.nav_my'), $html);
        $this->assertStringContainsString('href="'.route('challenges.create').'"', $html);
        $this->assertStringNotContainsString('href="'.route('leaderboard').'"', $html);
    }

    public function test_bottom_nav_hidden_on_the_form(): void
    {
        $user = User::factory()->create(['username' => 'dan']);
        $challenge = Challenge::factory()->withOriginal()->create();

        // The desktop header still links to My Challenges, so assert on the bottom-nav container itself.
        $bottomNav = 'fixed bottom-0 inset-x-0 z-40';
        $this->actingAs($user)->get(route('challenges.create'))->assertOk()->assertDontSee($bottomNav, false);
        $this->actingAs($user)->get(route('challenges.respond', $challenge->slug))->assertOk()->assertDontSee($bottomNav, false);
        $this->actingAs($user)->get(route('challenges.mine'))->assertOk()->assertSee($bottomNav, false);
    }

    public function test_battles_still_work_when_flag_is_on(): void
    {
        config(['versus.battles_enabled' => true]);

        $this->get('/')->assertOk();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ChallengesNavigationTest`
Expected: FAIL — `/` returns 200 instead of redirecting.

- [ ] **Step 3: Middleware and routes**

`app/Http/Middleware/EnsureBattlesEnabled.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBattlesEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('versus.battles_enabled')) {
            return redirect()->route('challenges.index');
        }

        return $next($request);
    }
}
```

In `bootstrap/app.php`, inside `->withMiddleware(function (Middleware $middleware): void {` add (with `use App\Http\Middleware\EnsureBattlesEnabled;`):

```php
        $middleware->alias([
            'battles' => EnsureBattlesEnabled::class,
        ]);
```

In `routes/web.php` append `->middleware('battles')` (or add `'battles'` to the existing middleware array) on exactly these routes:

```php
Route::get('/', BattleIndex::class)->middleware('battles')->name('home');
Route::get('/battles', BattleIndex::class)->middleware('battles')->name('battles.index');
Route::middleware(['auth', 'verified', 'battles'])->get('/battles/create', BattleCreate::class)->name('battles.create');
Route::get('/battles/{battle:slug}', BattleShow::class)->middleware('battles')->name('battles.show');
Route::get('/categories/{category:slug}', CategoryShow::class)->middleware('battles')->name('categories.show');
Route::get('/leaderboard', Leaderboard::class)->middleware('battles')->name('leaderboard');
```

and inside the auth group:

```php
    Route::get('/feed', FeedPage::class)->middleware('battles')->name('feed');
    Route::get('/wallet', WalletPage::class)->middleware('battles')->name('wallet');
```

The pool-total JSON endpoints, profile and auth routes stay untouched.

- [ ] **Step 4: Icons**

`resources/views/components/icon/bolt.blade.php`:

```blade
<svg {{ $attributes->merge(['class' => 'h-6 w-6', 'fill' => 'none', 'stroke' => 'currentColor', 'viewBox' => '0 0 24 24']) }}
     stroke-width="2" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" d="M13 3 4.5 13.5H12L11 21l8.5-10.5H12L13 3Z" />
</svg>
```

`resources/views/components/icon/swords.blade.php`:

```blade
<svg {{ $attributes->merge(['class' => 'h-6 w-6', 'fill' => 'none', 'stroke' => 'currentColor', 'viewBox' => '0 0 24 24']) }}
     stroke-width="2" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4l9 9m-2.5 2.5L13 18l2-2-2.5-2.5M20 4l-9 9m2.5 2.5L11 18l-2-2 2.5-2.5M5 17l2 2M19 17l-2 2" />
</svg>
```

- [ ] **Step 5: Bottom nav**

In `resources/views/layouts/bottom-nav.blade.php`, directly after the closing `];` of `$tabs` and before `@endphp`, add:

```php
    if (! config('versus.battles_enabled')) {
        $tabs = [
            ['route' => 'home', 'match' => ['home'], 'label' => __('nav.home'), 'icon' => 'home'],
            ['route' => 'challenges.index', 'match' => ['challenges.index', 'challenges.show'], 'label' => __('challenges.nav_challenges'), 'icon' => 'bolt'],
            ['route' => 'challenges.create', 'match' => ['challenges.create'], 'label' => __('challenges.publish'), 'icon' => 'plus', 'fab' => true],
            ['route' => 'challenges.mine', 'match' => ['challenges.mine'], 'label' => __('challenges.nav_my'), 'icon' => 'swords', 'auth' => true],
            ['route' => 'profile.edit', 'match' => ['profile.*'], 'label' => __('nav.profile'), 'icon' => 'user'],
        ];
    }
```

> Home points at `/`, which redirects to the feed until the Home showcase (sub-project 8) exists.

- [ ] **Step 6: Layout and header**

In `resources/views/layouts/app.blade.php`:
- wrap `@include('layouts.navigation')` as:

```blade
            @unless (request()->routeIs('challenges.index', 'challenges.show'))
                @include('layouts.navigation')
            @endunless
```

- wrap `@include('layouts.bottom-nav')` as:

```blade
            @unless (request()->routeIs('challenges.create', 'challenges.respond'))
                @include('layouts.bottom-nav')
            @endunless
```

In `resources/views/layouts/navigation.blade.php`, replace the contents of `<div class="hidden sm:flex items-center gap-6 text-sm">` with:

```blade
                    @if (config('versus.battles_enabled'))
                        @auth
                            <a href="{{ route('feed') }}"
                               class="transition {{ request()->routeIs('feed') ? 'text-white' : 'text-white/60 hover:text-white' }}">
                                {{ __('nav.feed') }}
                            </a>
                        @endauth
                        <a href="{{ route('leaderboard') }}"
                           class="transition {{ request()->routeIs('leaderboard') ? 'text-white' : 'text-white/60 hover:text-white' }}">
                            {{ __('nav.leaderboard') }}
                        </a>
                        @auth
                            <a href="{{ route('battles.create') }}"
                               class="transition {{ request()->routeIs('battles.create') ? 'text-white' : 'text-white/60 hover:text-white' }}">
                                {{ __('nav.create_battle') }}
                            </a>
                        @endauth
                    @else
                        <a href="{{ route('challenges.index') }}" class="text-white/60 transition hover:text-white">{{ __('challenges.nav_challenges') }}</a>
                        @auth
                            <a href="{{ route('challenges.mine') }}"
                               class="transition {{ request()->routeIs('challenges.mine') ? 'text-white' : 'text-white/60 hover:text-white' }}">
                                {{ __('challenges.nav_my') }}
                            </a>
                            <a href="{{ route('challenges.create') }}" class="text-white/60 transition hover:text-white">{{ __('challenges.publish') }}</a>
                        @endauth
                    @endif
```

and wrap the wallet dropdown link (`<x-dropdown-link :href="route('wallet')" ...>...</x-dropdown-link>`) in `@if (config('versus.battles_enabled')) ... @endif`.

- [ ] **Step 7: Run tests**

Run: `php artisan test --filter="ChallengesNavigationTest|BottomNavTest|HeaderCreateBattleLinkTest"` → PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Middleware/EnsureBattlesEnabled.php bootstrap/app.php routes/web.php resources/views/components/icon/bolt.blade.php resources/views/components/icon/swords.blade.php resources/views/layouts/bottom-nav.blade.php resources/views/layouts/navigation.blade.php resources/views/layouts/app.blade.php tests/Feature/Challenges/ChallengesNavigationTest.php
git commit -m "feat(challenges): hide battles behind flag, challenge navigation

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 18: Full gate and end-to-end check

**Files:** none new (fixes only, if the gate finds issues).

- [ ] **Step 1: Style, static analysis, tests**

Run: `make pint && make stan && make test`
Expected: Pint clean, Larastan level 6 no new errors (use `npm run stan` inside workspace if memory runs out — see memory note), all tests green. Fix any finding in the file it points at; do not add new baseline entries.

- [ ] **Step 2: Local end-to-end with real ffmpeg**

With `VERSUS_BATTLES_ENABLED=false` in `.env`:

1. `docker compose build php && make up`, `make migrate`, `make art CMD=storage:link`, `make npm CMD="run build"`.
2. In separate shells: `make art CMD=queue:work` and `make art CMD=schedule:work`.
3. User A creates a challenge with a real 15 s phone video (24 h). Within a minute the bell shows "Your challenge … is live"; `/challenges` plays it.
4. User B opens the share link, accepts, uploads a response; A gets "… responded"; the feed shows `Response 1/1`.
5. User C votes for B, then moves the vote to A (confirm dialog); counts update in place.
6. `make art CMD="tinker --execute=\"App\\Models\\Challenge::latest()->first()->update(['ends_at' => now()->subMinute()])\""`; within a minute A, B receive results; My Challenges shows `Over` + winner.
7. Upload a 70 s video → rejected client-side; upload a renamed `.txt` as `.mp4` → processing fails and the bell reports it.

- [ ] **Step 3: Commit any fixes**

```bash
git add -A
git commit -m "fix(challenges): address gate findings

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

(Skip if nothing changed.)

# Notification Bell Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire the placeholder bell icon to real in-app notifications (battle settled, referral payout, comment reply, comment like) with an unread badge, dropdown, and a "ding" sound on new notifications.

**Architecture:** Laravel native database notifications (stock `notifications` table, `Notifiable` on `User`). Actions collect events inside their `DB::transaction` and send notifications **after commit**, best-effort (`try/catch` + `report()`). A `NotificationBell` Livewire component replaces the placeholder button: `wire:poll.visible.60s` refreshes the unread count, opening the dropdown marks all read, Alpine plays a sound when the server dispatches a `notification-ding` browser event.

**Tech Stack:** Laravel 13 · Livewire 4 · Alpine · Tailwind · Pest 4 (SQLite `:memory:` in tests).

**Spec:** `docs/superpowers/specs/2026-07-16-notification-bell-design.md`

## Global Constraints

- All commands run through the workspace container: `make art CMD="..."`, `make pint`, `make test`. Never host `php`/`composer`.
- Larastan needs memory: run `make ws` then `vendor/bin/phpstan analyse --memory-limit=512M` (plain `make stan` may crash at 128M).
- Single test: `make art CMD="test --filter=test_name"`.
- New user-facing strings go to **both** `lang/en/notifications.php` and `lang/ru/notifications.php`.
- Money values rounded to 2 decimals; use existing amounts as-is (they are already rounded by the actions).
- `config('versus.mechanics.stomp_threshold')` is **0.90** — in settlement tests keep the winning side's share of total weight below 0.90 or the battle voids (refunds) instead of paying winners.
- A tie sends the battle to `Battle::STATUS_LAST_SHOT` (not settled) — **no notifications** in that case.
- Never notify a user about their own action (self-reply, self-like).
- Notification sending is best-effort: wrapped in `try/catch (\Throwable)` + `report()`, always after the enclosing `DB::transaction` has committed.
- Commit messages end with `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`.

---

### Task 1: Notifications table + notification classes

**Files:**
- Create: `database/migrations/2026_07_16_000000_create_notifications_table.php`
- Create: `app/Notifications/BattleSettled.php`
- Create: `app/Notifications/ReferralPayout.php`
- Create: `app/Notifications/CommentReplied.php`
- Create: `app/Notifications/CommentLiked.php`
- Test: `tests/Feature/Notifications/NotificationPayloadTest.php`

**Interfaces:**
- Consumes: `App\Models\Battle` (`id`, `slug`, `title`), `App\Models\Comment` (`id`), `App\Models\User` (`name`), `Notifiable` already on `User`.
- Produces (later tasks rely on these exact constructors):
  - `new BattleSettled(Battle $battle, string $result, float $amount)` with consts `BattleSettled::RESULT_WON = 'won'`, `RESULT_LOST = 'lost'`, `RESULT_REFUNDED = 'refunded'`
  - `new ReferralPayout(Battle $battle, string $refereeName, float $amount)`
  - `new CommentReplied(Battle $battle, Comment $comment, User $actor)`
  - `new CommentLiked(Battle $battle, Comment $comment, User $actor)`
  - All are database-channel only; `data` payloads exactly as asserted in the test below.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Notifications/NotificationPayloadTest.php`:

```php
<?php

namespace Tests\Feature\Notifications;

use App\Models\Battle;
use App\Models\Comment;
use App\Models\User;
use App\Notifications\BattleSettled;
use App\Notifications\CommentLiked;
use App\Notifications\CommentReplied;
use App\Notifications\ReferralPayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_battle_settled_payload_is_stored_in_database(): void
    {
        $user = User::factory()->create();
        $battle = Battle::factory()->create();

        $user->notify(new BattleSettled($battle, BattleSettled::RESULT_WON, 110.5));

        $notification = $user->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame([
            'battle_id' => $battle->id,
            'battle_slug' => $battle->slug,
            'battle_title' => $battle->title,
            'result' => 'won',
            'amount' => 110.5,
        ], $notification->data);
    }

    public function test_referral_payout_payload(): void
    {
        $user = User::factory()->create();
        $battle = Battle::factory()->create();

        $user->notify(new ReferralPayout($battle, 'Alice', 11.05));

        $this->assertSame([
            'battle_id' => $battle->id,
            'battle_slug' => $battle->slug,
            'battle_title' => $battle->title,
            'referee_name' => 'Alice',
            'amount' => 11.05,
        ], $user->notifications()->first()->data);
    }

    public function test_comment_replied_and_liked_payloads(): void
    {
        $author = User::factory()->create();
        $actor = User::factory()->create(['name' => 'Bob']);
        $battle = Battle::factory()->create();
        $comment = Comment::create([
            'user_id' => $author->id,
            'battle_id' => $battle->id,
            'body' => 'hello',
            'side' => Battle::SIDE_A,
        ]);

        $author->notify(new CommentReplied($battle, $comment, $actor));
        $author->notify(new CommentLiked($battle, $comment, $actor));

        $expected = [
            'battle_id' => $battle->id,
            'battle_slug' => $battle->slug,
            'battle_title' => $battle->title,
            'comment_id' => $comment->id,
            'actor_name' => 'Bob',
        ];
        $payloads = $author->notifications()->pluck('data');
        $this->assertCount(2, $payloads);
        $this->assertSame($expected, $payloads[0]);
        $this->assertSame($expected, $payloads[1]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `make art CMD="test --filter=NotificationPayloadTest"`
Expected: FAIL — `Class "App\Notifications\BattleSettled" not found`.

- [ ] **Step 3: Create the migration**

Run: `make art CMD="make:notifications-table"` then `make art CMD="migrate"`.
This generates the stock migration (uuid PK, `notifiable` morph, `type`, JSON `data`, `read_at`). Rename the generated file to have today's date prefix if artisan used a different one — content stays stock, do not edit it.

- [ ] **Step 4: Write the notification classes**

`app/Notifications/BattleSettled.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Battle;
use Illuminate\Notifications\Notification;

class BattleSettled extends Notification
{
    public const RESULT_WON = 'won';

    public const RESULT_LOST = 'lost';

    public const RESULT_REFUNDED = 'refunded';

    public function __construct(
        private readonly Battle $battle,
        private readonly string $result,
        private readonly float $amount,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'battle_id' => $this->battle->id,
            'battle_slug' => $this->battle->slug,
            'battle_title' => $this->battle->title,
            'result' => $this->result,
            'amount' => $this->amount,
        ];
    }
}
```

`app/Notifications/ReferralPayout.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Battle;
use Illuminate\Notifications\Notification;

class ReferralPayout extends Notification
{
    public function __construct(
        private readonly Battle $battle,
        private readonly string $refereeName,
        private readonly float $amount,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'battle_id' => $this->battle->id,
            'battle_slug' => $this->battle->slug,
            'battle_title' => $this->battle->title,
            'referee_name' => $this->refereeName,
            'amount' => $this->amount,
        ];
    }
}
```

`app/Notifications/CommentReplied.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Battle;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Notifications\Notification;

class CommentReplied extends Notification
{
    public function __construct(
        private readonly Battle $battle,
        private readonly Comment $comment,
        private readonly User $actor,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'battle_id' => $this->battle->id,
            'battle_slug' => $this->battle->slug,
            'battle_title' => $this->battle->title,
            'comment_id' => $this->comment->id,
            'actor_name' => $this->actor->name,
        ];
    }
}
```

`app/Notifications/CommentLiked.php` — identical to `CommentReplied` except the class name:

```php
<?php

namespace App\Notifications;

use App\Models\Battle;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Notifications\Notification;

class CommentLiked extends Notification
{
    public function __construct(
        private readonly Battle $battle,
        private readonly Comment $comment,
        private readonly User $actor,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'battle_id' => $this->battle->id,
            'battle_slug' => $this->battle->slug,
            'battle_title' => $this->battle->title,
            'comment_id' => $this->comment->id,
            'actor_name' => $this->actor->name,
        ];
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `make art CMD="test --filter=NotificationPayloadTest"`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add database/migrations app/Notifications tests/Feature/Notifications
git commit -m "feat(notifications): notifications table + 4 database notification classes

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 2: SettleBattleAction sends BattleSettled + ReferralPayout

**Files:**
- Modify: `app/Actions/Battles/SettleBattleAction.php`
- Test: `tests/Feature/Notifications/SettlementNotificationsTest.php`

**Interfaces:**
- Consumes: `BattleSettled` / `ReferralPayout` constructors and `RESULT_*` consts from Task 1.
- Produces: no new public API — `__invoke(Battle): Battle` unchanged; notifications are a side effect after commit, only when the battle ends `STATUS_SETTLED`.

Key mechanics of the existing action (read it first):
- Everything runs inside one `DB::transaction`. Branches: empty pool (`VOID_EMPTY`, no votes → nothing to notify), stomp refund (`refundAll`), tie → `STATUS_LAST_SHOT` (**not settled — no notifications**), void refund (`refundAll`), normal payout loop (winners in `id` order, last absorbs residue; `payReferral` per winning vote).
- A user can hold several votes, even on both sides. One `BattleSettled` per user: winners get `won` with their **summed** payout; refund paths get `refunded` with summed refund; losing-side voters who won nothing get `lost` with amount `0`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Notifications/SettlementNotificationsTest.php`:

```php
<?php

namespace Tests\Feature\Notifications;

use App\Actions\Battles\CastVoteAction;
use App\Actions\Battles\SettleBattleAction;
use App\Models\Battle;
use App\Models\User;
use App\Notifications\BattleSettled;
use App\Notifications\ReferralPayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SettlementNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function vote(): CastVoteAction
    {
        return app(CastVoteAction::class);
    }

    private function closeAndSettle(Battle $battle): Battle
    {
        $battle->status = Battle::STATUS_CLOSED;
        $battle->closes_at = now()->subMinute();
        $battle->save();

        return app(SettleBattleAction::class)($battle);
    }

    public function test_winners_and_losers_are_notified_with_result_and_amount(): void
    {
        Notification::fake();

        $winner = User::factory()->create(['balance' => 1000]);
        $loser = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();

        ($this->vote())($winner, $battle, Battle::SIDE_A, 300);
        ($this->vote())($loser, $battle, Battle::SIDE_B, 200);

        $this->closeAndSettle($battle);

        // pool 500, winners share 88% = 440, single winner takes it all
        Notification::assertSentTo($winner, BattleSettled::class, function (BattleSettled $n) use ($winner) {
            $data = $n->toDatabase($winner);

            return $data['result'] === BattleSettled::RESULT_WON && $data['amount'] === 440.0;
        });
        Notification::assertSentTo($loser, BattleSettled::class, function (BattleSettled $n) use ($loser) {
            $data = $n->toDatabase($loser);

            return $data['result'] === BattleSettled::RESULT_LOST && $data['amount'] === 0.0;
        });
    }

    public function test_multi_vote_winner_gets_one_notification_with_summed_payout(): void
    {
        Notification::fake();

        $winner = User::factory()->create(['balance' => 1000]);
        $loser = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();

        ($this->vote())($winner, $battle, Battle::SIDE_A, 100);
        ($this->vote())($winner, $battle, Battle::SIDE_A, 200);
        ($this->vote())($loser, $battle, Battle::SIDE_B, 200);

        $this->closeAndSettle($battle);

        Notification::assertSentToTimes($winner, BattleSettled::class, 1);
        Notification::assertSentTo($winner, BattleSettled::class, function (BattleSettled $n) use ($winner) {
            $data = $n->toDatabase($winner);

            return $data['result'] === BattleSettled::RESULT_WON && $data['amount'] === 440.0;
        });
    }

    public function test_stomp_refund_notifies_refunded_with_stake(): void
    {
        Notification::fake();

        $a = User::factory()->create(['balance' => 1000]);
        $b = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();

        // 950 / 1000 = 0.95 >= stomp_threshold 0.90 → void + refund
        ($this->vote())($a, $battle, Battle::SIDE_A, 950);
        ($this->vote())($b, $battle, Battle::SIDE_B, 50);

        $this->closeAndSettle($battle);

        Notification::assertSentTo($a, BattleSettled::class, function (BattleSettled $n) use ($a) {
            $data = $n->toDatabase($a);

            return $data['result'] === BattleSettled::RESULT_REFUNDED && $data['amount'] === 950.0;
        });
        Notification::assertSentTo($b, BattleSettled::class, function (BattleSettled $n) use ($b) {
            $data = $n->toDatabase($b);

            return $data['result'] === BattleSettled::RESULT_REFUNDED && $data['amount'] === 50.0;
        });
    }

    public function test_tie_goes_to_last_shot_and_sends_nothing(): void
    {
        Notification::fake();

        $a = User::factory()->create(['balance' => 1000]);
        $b = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();

        ($this->vote())($a, $battle, Battle::SIDE_A, 100);
        ($this->vote())($b, $battle, Battle::SIDE_B, 100);

        $this->closeAndSettle($battle);

        $this->assertSame(Battle::STATUS_LAST_SHOT, $battle->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_referrer_is_notified_about_referral_payout(): void
    {
        Notification::fake();

        $referrer = User::factory()->create(['balance' => 1000]);
        $referee = User::factory()->create(['balance' => 1000, 'referred_by_id' => $referrer->id]);
        $loser = User::factory()->create(['balance' => 1000]);
        $battle = Battle::factory()->create();

        ($this->vote())($referee, $battle, Battle::SIDE_A, 300);
        ($this->vote())($loser, $battle, Battle::SIDE_B, 200);

        $this->closeAndSettle($battle);

        // referee payout 440 → referral cut 10% = 44, capped by reward pool (4% of 500 = 20)
        Notification::assertSentTo($referrer, ReferralPayout::class, function (ReferralPayout $n) use ($referrer, $referee) {
            $data = $n->toDatabase($referrer);

            return $data['referee_name'] === $referee->name && $data['amount'] === 20.0;
        });
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `make art CMD="test --filter=SettlementNotificationsTest"`
Expected: FAIL — `assertSentTo` finds nothing (notifications not sent yet). The `test_tie...` test may already pass; that is fine.

- [ ] **Step 3: Implement collection + post-commit send in SettleBattleAction**

In `app/Actions/Battles/SettleBattleAction.php`:

Add imports:

```php
use App\Notifications\BattleSettled;
use App\Notifications\ReferralPayout;
use Throwable;
```

Add instance properties above `__invoke`:

```php
/** @var array<int, array{result: string, amount: float}> */
private array $voterOutcomes = [];

/** @var list<array{referrer_id: int, referee_id: int, amount: float}> */
private array $referralRewards = [];
```

Restructure `__invoke` so the transaction result is captured and notifications go out after commit (the whole existing body stays inside the closure):

```php
public function __invoke(Battle $battle): Battle
{
    $this->voterOutcomes = [];
    $this->referralRewards = [];

    $settled = DB::transaction(function () use ($battle): Battle {
        // ... existing body unchanged, plus the three collection points below ...
    });

    $this->sendNotifications($settled);

    return $settled;
}
```

Collection point 1 — in `refundAll()`, after `$vote->save()` inside the loop:

```php
$this->recordOutcome($user->id, BattleSettled::RESULT_REFUNDED, $amount);
```

Collection point 2 — in the winners loop of `__invoke`, after the `Transaction::create([...])` for the payout:

```php
$this->recordOutcome($winner->id, BattleSettled::RESULT_WON, $payout);
```

Collection point 3 — still in `__invoke`, right after the winners `foreach` ends (before `$battle->status = Battle::STATUS_SETTLED;`), record losers:

```php
$losingSide = $winningSide === Battle::SIDE_A ? Battle::SIDE_B : Battle::SIDE_A;
$loserIds = Vote::where('battle_id', $battle->id)
    ->where('side', $losingSide)
    ->distinct()
    ->pluck('user_id');

foreach ($loserIds as $loserId) {
    if (! isset($this->voterOutcomes[$loserId])) {
        $this->voterOutcomes[$loserId] = ['result' => BattleSettled::RESULT_LOST, 'amount' => 0.0];
    }
}
```

Collection point 4 — in `payReferral()`, after the referrer's `Transaction::create([...])`:

```php
$this->referralRewards[] = [
    'referrer_id' => $referrer->id,
    'referee_id' => $winnerId,
    'amount' => $reward,
];
```

New private methods at the bottom of the class:

```php
private function recordOutcome(int $userId, string $result, float $amount): void
{
    if (! isset($this->voterOutcomes[$userId])) {
        $this->voterOutcomes[$userId] = ['result' => $result, 'amount' => 0.0];
    }

    $this->voterOutcomes[$userId]['amount'] = $this->round($this->voterOutcomes[$userId]['amount'] + $amount);
}

private function sendNotifications(Battle $battle): void
{
    if ($battle->status !== Battle::STATUS_SETTLED) {
        return;
    }

    try {
        $userIds = array_unique(array_merge(
            array_keys($this->voterOutcomes),
            array_column($this->referralRewards, 'referrer_id'),
            array_column($this->referralRewards, 'referee_id'),
        ));
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        foreach ($this->voterOutcomes as $userId => $outcome) {
            $users->get($userId)?->notify(
                new BattleSettled($battle, $outcome['result'], $outcome['amount'])
            );
        }

        foreach ($this->referralRewards as $reward) {
            $referee = $users->get($reward['referee_id']);
            $users->get($reward['referrer_id'])?->notify(
                new ReferralPayout($battle, $referee->name ?? '', $reward['amount'])
            );
        }
    } catch (Throwable $e) {
        report($e);
    }
}
```

Note: `sendNotifications` returns early for `STATUS_LAST_SHOT` (tie) and any non-settled outcome, so nothing collected leaks across states. The `VOID_EMPTY` branch collects nothing (no votes).

- [ ] **Step 4: Run tests to verify they pass**

Run: `make art CMD="test --filter=SettlementNotificationsTest"`
Expected: PASS (5 tests).

- [ ] **Step 5: Run the whole settlement suite to check for regressions**

Run: `make art CMD="test --filter=Settlement"` and `make art CMD="test --filter=ReferralPayoutTest"`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Battles/SettleBattleAction.php tests/Feature/Notifications/SettlementNotificationsTest.php
git commit -m "feat(notifications): notify voters and referrers when a battle settles

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 3: PostCommentAction sends CommentReplied

**Files:**
- Modify: `app/Actions/Comments/PostCommentAction.php`
- Test: `tests/Feature/Notifications/CommentNotificationsTest.php`

**Interfaces:**
- Consumes: `CommentReplied` constructor from Task 1; `Comment->user` belongsTo relation (exists).
- Produces: no API change — `__invoke(User, Battle, string, ?string, ?Comment, ?User): Comment` unchanged.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Notifications/CommentNotificationsTest.php`:

```php
<?php

namespace Tests\Feature\Notifications;

use App\Actions\Comments\PostCommentAction;
use App\Models\Battle;
use App\Models\User;
use App\Notifications\CommentReplied;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CommentNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reply_notifies_parent_comment_author(): void
    {
        Notification::fake();

        $author = User::factory()->create();
        $replier = User::factory()->create();
        $battle = Battle::factory()->create();

        $parent = app(PostCommentAction::class)($author, $battle, 'root', Battle::SIDE_A);
        $reply = app(PostCommentAction::class)($replier, $battle, 'reply', null, $parent);

        Notification::assertSentTo($author, CommentReplied::class, function (CommentReplied $n) use ($author, $reply, $replier) {
            $data = $n->toDatabase($author);

            return $data['comment_id'] === $reply->id && $data['actor_name'] === $replier->name;
        });
    }

    public function test_self_reply_notifies_nobody(): void
    {
        Notification::fake();

        $author = User::factory()->create();
        $battle = Battle::factory()->create();

        $parent = app(PostCommentAction::class)($author, $battle, 'root', Battle::SIDE_A);
        app(PostCommentAction::class)($author, $battle, 'self reply', null, $parent);

        Notification::assertNothingSent();
    }

    public function test_reply_to_user_distinct_from_parent_author_is_also_notified(): void
    {
        Notification::fake();

        $author = User::factory()->create();
        $mentioned = User::factory()->create();
        $replier = User::factory()->create();
        $battle = Battle::factory()->create();

        $parent = app(PostCommentAction::class)($author, $battle, 'root', Battle::SIDE_A);
        app(PostCommentAction::class)($replier, $battle, 'reply', null, $parent, $mentioned);

        Notification::assertSentTo($author, CommentReplied::class);
        Notification::assertSentTo($mentioned, CommentReplied::class);
    }

    public function test_top_level_comment_notifies_nobody(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $battle = Battle::factory()->create();

        app(PostCommentAction::class)($user, $battle, 'top level', Battle::SIDE_A);

        Notification::assertNothingSent();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `make art CMD="test --filter=CommentNotificationsTest"`
Expected: FAIL on the two "notifies" tests; the two "nothing sent" tests already pass.

- [ ] **Step 3: Implement**

In `app/Actions/Comments/PostCommentAction.php` add imports:

```php
use App\Notifications\CommentReplied;
use Throwable;
```

Capture the transaction result and notify after commit — change the `return DB::transaction(...)` tail of `__invoke` to:

```php
$comment = DB::transaction(function () use ($user, $battle, $body, $side, $parent, $replyTo) {
    // ... existing closure body unchanged ...
});

$this->notifyReply($user, $battle, $comment, $parent, $replyTo);

return $comment;
```

Add the private method:

```php
private function notifyReply(User $actor, Battle $battle, Comment $comment, ?Comment $parent, ?User $replyTo): void
{
    if ($parent === null) {
        return;
    }

    try {
        $recipients = collect([$parent->user, $replyTo])
            ->filter()
            ->unique('id')
            ->reject(fn (User $recipient): bool => $recipient->id === $actor->id);

        foreach ($recipients as $recipient) {
            $recipient->notify(new CommentReplied($battle, $comment, $actor));
        }
    } catch (Throwable $e) {
        report($e);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `make art CMD="test --filter=CommentNotificationsTest"`
Expected: PASS (4 tests). Also run `make art CMD="test --filter=Comment"` to catch regressions in existing comment tests.

- [ ] **Step 5: Commit**

```bash
git add app/Actions/Comments/PostCommentAction.php tests/Feature/Notifications/CommentNotificationsTest.php
git commit -m "feat(notifications): notify comment author on reply

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 4: LikeCommentAction sends CommentLiked

**Files:**
- Modify: `app/Actions/Comments/LikeCommentAction.php`
- Test: `tests/Feature/Notifications/LikeNotificationsTest.php`

**Interfaces:**
- Consumes: `CommentLiked` constructor from Task 1.
- Produces: no API change — `__invoke(User, Comment, Battle): array{already_liked: bool, liked: bool, likes_count: int}` unchanged.

Constraint from the existing action: liking requires the comment to have a side (`A`/`B`) and the battle to be open for voting; a repeat like returns `already_liked => true` without changes. Liking also costs the liker 1 token (`LIKE_AMOUNT`), so give the liker balance in tests.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Notifications/LikeNotificationsTest.php`:

```php
<?php

namespace Tests\Feature\Notifications;

use App\Actions\Comments\LikeCommentAction;
use App\Actions\Comments\PostCommentAction;
use App\Models\Battle;
use App\Models\Comment;
use App\Models\User;
use App\Notifications\CommentLiked;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LikeNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeComment(User $author, Battle $battle): Comment
    {
        return app(PostCommentAction::class)($author, $battle, 'nice take', Battle::SIDE_A);
    }

    public function test_first_like_notifies_comment_author(): void
    {
        Notification::fake();

        $author = User::factory()->create(['balance' => 100]);
        $liker = User::factory()->create(['balance' => 100]);
        $battle = Battle::factory()->create();
        $comment = $this->makeComment($author, $battle);

        app(LikeCommentAction::class)($liker, $comment, $battle);

        Notification::assertSentTo($author, CommentLiked::class, function (CommentLiked $n) use ($author, $comment, $liker) {
            $data = $n->toDatabase($author);

            return $data['comment_id'] === $comment->id && $data['actor_name'] === $liker->name;
        });
    }

    public function test_repeat_like_does_not_notify_again(): void
    {
        Notification::fake();

        $author = User::factory()->create(['balance' => 100]);
        $liker = User::factory()->create(['balance' => 100]);
        $battle = Battle::factory()->create();
        $comment = $this->makeComment($author, $battle);

        app(LikeCommentAction::class)($liker, $comment, $battle);
        app(LikeCommentAction::class)($liker, $comment, $battle);

        Notification::assertSentToTimes($author, CommentLiked::class, 1);
    }

    public function test_self_like_does_not_notify(): void
    {
        Notification::fake();

        $author = User::factory()->create(['balance' => 100]);
        $battle = Battle::factory()->create();
        $comment = $this->makeComment($author, $battle);

        app(LikeCommentAction::class)($author, $comment, $battle);

        Notification::assertNothingSent();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `make art CMD="test --filter=LikeNotificationsTest"`
Expected: FAIL on the first two; `test_self_like...` already passes.

- [ ] **Step 3: Implement**

In `app/Actions/Comments/LikeCommentAction.php` add imports:

```php
use App\Notifications\CommentLiked;
use Throwable;
```

Change the `return DB::transaction(...)` tail of `__invoke` to:

```php
$result = DB::transaction(function () use ($user, $comment, $battle) {
    // ... existing closure body unchanged ...
});

if (! $result['already_liked'] && $comment->user_id !== $user->id) {
    try {
        $comment->user?->notify(new CommentLiked($battle, $comment, $user));
    } catch (Throwable $e) {
        report($e);
    }
}

return $result;
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `make art CMD="test --filter=LikeNotificationsTest"`
Expected: PASS (3 tests). Also run `make art CMD="test --filter=Like"` for regressions.

- [ ] **Step 5: Commit**

```bash
git add app/Actions/Comments/LikeCommentAction.php tests/Feature/Notifications/LikeNotificationsTest.php
git commit -m "feat(notifications): notify comment author on first like

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 5: NotificationBell Livewire component + navigation wiring + i18n

**Files:**
- Create: `app/Livewire/NotificationBell.php`
- Create: `resources/views/livewire/notification-bell.blade.php`
- Create: `lang/en/notifications.php`
- Create: `lang/ru/notifications.php`
- Modify: `resources/views/layouts/navigation.blade.php` (replace the placeholder bell `<button>` at lines ~48–57; keep the Messages placeholder button)
- Modify: `resources/views/components/comment-thread/item.blade.php` (add `id="comment-{{ $comment->id }}"` to the root element so notification anchors scroll)
- Test: `tests/Feature/Livewire/NotificationBellTest.php`

**Interfaces:**
- Consumes: notification `data` payloads from Task 1 (`battle_slug`, `battle_title`, `result`, `amount`, `referee_name`, `comment_id`, `actor_name`); route `battles.show` (slug-bound).
- Produces: Livewire component `notification-bell` with public `int $unreadCount`, `bool $open`, `array $freshIds`, methods `toggle()`, `refreshCount()`; server-dispatched browser event **`notification-ding`** (Task 6's sound hooks onto it).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Livewire/NotificationBellTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\NotificationBell;
use App\Models\Battle;
use App\Models\User;
use App\Notifications\BattleSettled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    private function notify(User $user, float $amount = 44.0): Battle
    {
        $battle = Battle::factory()->create();
        $user->notify(new BattleSettled($battle, BattleSettled::RESULT_WON, $amount));

        return $battle;
    }

    public function test_badge_shows_unread_count(): void
    {
        $user = User::factory()->create();
        $this->notify($user);
        $this->notify($user);

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->assertSet('unreadCount', 2);
    }

    public function test_opening_dropdown_marks_all_read_and_lists_messages(): void
    {
        $user = User::factory()->create();
        $battle = $this->notify($user);

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->call('toggle')
            ->assertSet('open', true)
            ->assertSet('unreadCount', 0)
            ->assertSee($battle->title);

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_refresh_count_dispatches_ding_when_count_grows(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(NotificationBell::class);

        $this->notify($user);

        $component->call('refreshCount')
            ->assertSet('unreadCount', 1)
            ->assertDispatched('notification-ding');

        $component->call('refreshCount')
            ->assertNotDispatched('notification-ding');
    }

    public function test_bell_rendered_for_auth_users_only(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertSeeLivewire(NotificationBell::class);
        auth()->logout();
        $this->get('/')->assertDontSeeLivewire(NotificationBell::class);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `make art CMD="test --filter=NotificationBellTest"`
Expected: FAIL — `Class "App\Livewire\NotificationBell" not found`.

- [ ] **Step 3: Create the lang files**

`lang/en/notifications.php`:

```php
<?php

return [
    'title' => 'Notifications',
    'empty' => 'No notifications yet',
    'battle_won' => 'You won :amount tokens in ":battle"!',
    'battle_lost' => 'Your side lost in ":battle".',
    'battle_refunded' => 'Your stake of :amount tokens was refunded in ":battle".',
    'referral_payout' => 'Referral bonus: :amount tokens from :name\'s win in ":battle".',
    'comment_replied' => ':name replied to your comment in ":battle".',
    'comment_liked' => ':name liked your comment in ":battle".',
    'sound_on' => 'Sound on',
    'sound_off' => 'Sound off',
];
```

`lang/ru/notifications.php`:

```php
<?php

return [
    'title' => 'Уведомления',
    'empty' => 'Пока нет уведомлений',
    'battle_won' => 'Вы выиграли :amount токенов в баттле «:battle»!',
    'battle_lost' => 'Ваша сторона проиграла в баттле «:battle».',
    'battle_refunded' => 'Ставка :amount токенов возвращена — баттл «:battle» завершился без победителя.',
    'referral_payout' => 'Реферальный бонус :amount токенов — :name выиграл в баттле «:battle».',
    'comment_replied' => ':name ответил(а) на ваш комментарий в баттле «:battle».',
    'comment_liked' => ':name оценил(а) ваш комментарий в баттле «:battle».',
    'sound_on' => 'Звук включён',
    'sound_off' => 'Звук выключен',
];
```

- [ ] **Step 4: Create the component**

`app/Livewire/NotificationBell.php`:

```php
<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Component;

class NotificationBell extends Component
{
    public bool $open = false;

    public int $unreadCount = 0;

    /** @var list<string> */
    public array $freshIds = [];

    public function mount(): void
    {
        $this->unreadCount = $this->user()->unreadNotifications()->count();
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;

        if ($this->open) {
            $unread = $this->user()->unreadNotifications;
            $this->freshIds = $unread->pluck('id')->all();
            $unread->markAsRead();
            $this->unreadCount = 0;
        } else {
            $this->freshIds = [];
        }
    }

    public function refreshCount(): void
    {
        $fresh = $this->user()->unreadNotifications()->count();

        if ($fresh > $this->unreadCount) {
            $this->dispatch('notification-ding');
        }

        $this->unreadCount = $fresh;
    }

    public function render(): View
    {
        $items = ! $this->open ? collect() : $this->user()
            ->notifications()
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'message' => $this->message($notification),
                'url' => $this->url($notification),
                'time' => $notification->created_at?->diffForHumans() ?? '',
                'fresh' => in_array($notification->id, $this->freshIds, true),
            ]);

        return view('livewire.notification-bell', ['items' => $items]);
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }

    private function message(DatabaseNotification $notification): string
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;
        $battle = (string) ($data['battle_title'] ?? '');

        return match (class_basename($notification->type)) {
            'BattleSettled' => __('notifications.battle_'.$data['result'], [
                'amount' => number_format((float) $data['amount'], 2),
                'battle' => $battle,
            ]),
            'ReferralPayout' => __('notifications.referral_payout', [
                'amount' => number_format((float) $data['amount'], 2),
                'name' => (string) $data['referee_name'],
                'battle' => $battle,
            ]),
            'CommentReplied' => __('notifications.comment_replied', [
                'name' => (string) $data['actor_name'],
                'battle' => $battle,
            ]),
            'CommentLiked' => __('notifications.comment_liked', [
                'name' => (string) $data['actor_name'],
                'battle' => $battle,
            ]),
            default => '',
        };
    }

    private function url(DatabaseNotification $notification): string
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;
        $url = route('battles.show', ['battle' => (string) $data['battle_slug']]);

        return isset($data['comment_id']) ? $url.'#comment-'.$data['comment_id'] : $url;
    }
}
```

- [ ] **Step 5: Create the view**

`resources/views/livewire/notification-bell.blade.php` (the `x-data` sound stub gets its real body in Task 6):

```blade
<div class="relative"
     wire:poll.visible.60s="refreshCount"
     x-data
     x-on:click.outside="$wire.open && $wire.toggle()"
     x-on:keydown.escape.window="$wire.open && $wire.toggle()">
    <button type="button"
            wire:click="toggle"
            class="relative p-2 rounded-full text-white/60 hover:text-white hover:bg-white/5 transition"
            aria-label="{{ __('notifications.title') }}">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
             stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
        </svg>
        @if ($unreadCount > 0)
            <span class="absolute -top-0.5 -right-0.5 min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    @if ($open)
        <div class="absolute right-0 mt-2 w-80 rounded-xl bg-navy-800 border border-white/10 shadow-xl z-50 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2.5 border-b border-white/10">
                <span class="text-sm font-semibold text-white">{{ __('notifications.title') }}</span>
                {{-- sound toggle added in Task 6 --}}
            </div>

            @if ($items->isEmpty())
                <p class="px-4 py-6 text-sm text-white/50 text-center">{{ __('notifications.empty') }}</p>
            @else
                <div class="max-h-96 overflow-y-auto divide-y divide-white/5">
                    @foreach ($items as $item)
                        <a href="{{ $item['url'] }}" wire:key="notification-{{ $item['id'] }}"
                           class="block px-4 py-3 hover:bg-white/5 transition {{ $item['fresh'] ? 'bg-white/[0.04]' : '' }}">
                            <p class="text-sm text-white/90 leading-snug">{{ $item['message'] }}</p>
                            <p class="mt-0.5 text-xs text-white/40">{{ $item['time'] }}</p>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
```

- [ ] **Step 6: Wire into navigation + comment anchors**

In `resources/views/layouts/navigation.blade.php`, replace the entire placeholder Notifications `<button type="button" ... aria-label="Notifications">...</button>` (the first button inside the `@auth` `hidden sm:flex` div) with:

```blade
<livewire:notification-bell />
```

Leave the Messages placeholder button untouched.

In `resources/views/components/comment-thread/item.blade.php`, add `id="comment-{{ $comment->id }}"` to the root element of the partial (read the file first; the root is the outermost element that wraps a single comment).

- [ ] **Step 7: Run tests to verify they pass**

Run: `make art CMD="test --filter=NotificationBellTest"`
Expected: PASS (4 tests). Also `make art CMD="test --filter=Navigation"` for layout regressions.

- [ ] **Step 8: Commit**

```bash
git add app/Livewire/NotificationBell.php resources/views/livewire/notification-bell.blade.php \
    lang/en/notifications.php lang/ru/notifications.php \
    resources/views/layouts/navigation.blade.php resources/views/components/comment-thread/item.blade.php \
    tests/Feature/Livewire/NotificationBellTest.php
git commit -m "feat(notifications): notification bell dropdown with unread badge and polling

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 6: Notification sound + toggle

**Files:**
- Create: `public/sounds/notification.wav` (generated, ~13 KB)
- Modify: `resources/views/livewire/notification-bell.blade.php` (Alpine sound logic + toggle button)

**Interfaces:**
- Consumes: browser event `notification-ding` dispatched by `NotificationBell::refreshCount()` (Task 5).
- Produces: `localStorage['versus_notification_sound']` (`'1'` on / `'0'` off, default on). Client-side only — no PHP tests; verified manually in Task 7.

- [ ] **Step 1: Generate the sound asset**

Create `scripts/generate-notification-sound.php` is NOT needed — run a one-off inside the workspace container. From the repo root:

```bash
make art CMD="tinker --execute=\"
\\\$rate = 22050; \\\$n = (int) (\\\$rate * 0.3); \\\$samples = '';
for (\\\$i = 0; \\\$i < \\\$n; \\\$i++) {
    \\\$t = \\\$i / \\\$rate;
    \\\$v = 0.5 * exp(-10 * \\\$t) * (sin(2 * M_PI * 880 * \\\$t) + 0.5 * sin(2 * M_PI * 1320 * \\\$t));
    \\\$samples .= pack('v', (int) (32767 * max(-1, min(1, \\\$v))) & 0xFFFF);
}
\\\$size = strlen(\\\$samples);
\\\$header = 'RIFF'.pack('V', 36 + \\\$size).'WAVEfmt '.pack('V', 16).pack('v', 1).pack('v', 1).pack('V', \\\$rate).pack('V', \\\$rate * 2).pack('v', 2).pack('v', 16).'data'.pack('V', \\\$size);
if (! is_dir(public_path('sounds'))) { mkdir(public_path('sounds')); }
file_put_contents(public_path('sounds/notification.wav'), \\\$header.\\\$samples);
echo 'written: '.filesize(public_path('sounds/notification.wav'));\""
```

If the shell-escaping fights back, instead write those same lines to a temp file `scratch-sound.php` wrapped in `<?php` + `require 'vendor/autoload.php'` is unnecessary — plain PHP works: run `make ws` → `php scratch-sound.php` with `public/sounds/notification.wav` as a relative path, then delete the temp file.

Expected: `public/sounds/notification.wav` exists, ~13 KB. Sanity check: `file public/sounds/notification.wav` reports `RIFF ... WAVE audio, mono 22050 Hz`.

- [ ] **Step 2: Add Alpine sound logic to the view**

In `resources/views/livewire/notification-bell.blade.php`, replace `x-data` on the root div with:

```blade
x-data="{
    soundEnabled: (localStorage.getItem('versus_notification_sound') ?? '1') === '1',
    toggleSound() {
        this.soundEnabled = ! this.soundEnabled;
        localStorage.setItem('versus_notification_sound', this.soundEnabled ? '1' : '0');
    },
    ding() {
        if (this.soundEnabled) {
            new Audio('{{ asset('sounds/notification.wav') }}').play().catch(() => {});
        }
    },
}"
x-on:notification-ding.window="ding()"
```

And replace the `{{-- sound toggle added in Task 6 --}}` placeholder in the dropdown header with:

```blade
<button type="button"
        x-on:click="toggleSound()"
        class="p-1 rounded text-white/50 hover:text-white transition"
        x-bind:title="soundEnabled ? '{{ __('notifications.sound_on') }}' : '{{ __('notifications.sound_off') }}'">
    <svg x-show="soundEnabled" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
         stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" />
    </svg>
    <svg x-show="! soundEnabled" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
         stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M17.25 9.75 19.5 12m0 0 2.25 2.25M19.5 12l2.25-2.25M19.5 12l-2.25 2.25m-10.5-6 4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" />
    </svg>
</button>
```

- [ ] **Step 3: Verify nothing broke server-side**

Run: `make art CMD="test --filter=NotificationBellTest"`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add public/sounds/notification.wav resources/views/livewire/notification-bell.blade.php
git commit -m "feat(notifications): ding sound on new notifications with localStorage toggle

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 7: Full gate + manual verification

**Files:** none new.

- [ ] **Step 1: Style**

Run: `make pint`
Expected: fixes applied or "PASS". Re-stage/commit if pint changed files (`git commit -am "style: pint"` with the co-author trailer).

- [ ] **Step 2: Static analysis**

Run: `make ws` then inside: `vendor/bin/phpstan analyse --memory-limit=512M`
Expected: `[OK] No errors`. Fix any new level-6 errors (do **not** grow the baseline).

- [ ] **Step 3: Full test suite**

Run: `make test`
Expected: all green.

- [ ] **Step 4: Manual smoke test in the browser**

1. `make up`, log in at http://versus.local/ (seed user or register).
2. In another shell: `make art CMD=tinker` → send yourself a notification:
   `User::first()->notify(new \App\Notifications\BattleSettled(\App\Models\Battle::first(), 'won', 42.0));`
3. Click somewhere on the page once (unlock audio), wait for the next poll (≤60 s with the tab visible) — badge shows **1** and the ding plays.
4. Open the bell: dropdown lists the message, badge clears; row links to the battle page.
5. Toggle the speaker icon off, repeat step 2–3: badge updates, **no** sound. Reload — the off state persists (localStorage).
6. Switch locale to RU and confirm the dropdown texts are Russian.

- [ ] **Step 5: Commit any leftovers, done**

```bash
git status   # should be clean; commit stragglers if any
```

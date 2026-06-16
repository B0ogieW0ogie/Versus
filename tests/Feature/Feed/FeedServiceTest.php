<?php

use App\Models\Battle;
use App\Models\Comment;
use App\Models\User;
use App\Models\Vote;
use App\Services\Feed\FeedEvent;
use App\Services\Feed\FeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function feed(User $viewer, string $filter = FeedService::FILTER_ALL)
{
    return app(FeedService::class)->events($viewer, $filter, null, 50);
}

test('create event appears for a followed user', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $viewer->following()->attach($author->id);

    $battle = Battle::factory()->create(['created_by_id' => $author->id]);

    $events = feed($viewer);

    expect($events)->toHaveCount(1)
        ->and($events->first()->type)->toBe(FeedEvent::TYPE_CREATE)
        ->and($events->first()->battle->id)->toBe($battle->id)
        ->and($events->first()->actor->id)->toBe($author->id);
});

test('vote event appears for a followed user', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $viewer->following()->attach($author->id);

    Vote::factory()->create(['user_id' => $author->id]);

    $events = feed($viewer, FeedService::FILTER_VOTES);

    expect($events)->toHaveCount(1)
        ->and($events->first()->type)->toBe(FeedEvent::TYPE_VOTE);
});

test('argue event carries the comment body', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $viewer->following()->attach($author->id);

    Comment::factory()->create(['user_id' => $author->id, 'body' => 'Because reasons']);

    $events = feed($viewer, FeedService::FILTER_ARGUMENTS);

    expect($events)->toHaveCount(1)
        ->and($events->first()->type)->toBe(FeedEvent::TYPE_ARGUE)
        ->and($events->first()->argumentText)->toBe('Because reasons');
});

test('win event when followed user voted the winning side', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $viewer->following()->attach($author->id);

    $battle = Battle::factory()->settled(Battle::SIDE_A)->create();
    Vote::factory()->create([
        'user_id' => $author->id,
        'battle_id' => $battle->id,
        'side' => Battle::SIDE_A,
    ]);

    $events = feed($viewer, FeedService::FILTER_RESULTS);

    expect($events)->toHaveCount(1)
        ->and($events->first()->type)->toBe(FeedEvent::TYPE_WIN);
});

test('lose event when followed user voted the losing side', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $viewer->following()->attach($author->id);

    $battle = Battle::factory()->settled(Battle::SIDE_A)->create();
    Vote::factory()->create([
        'user_id' => $author->id,
        'battle_id' => $battle->id,
        'side' => Battle::SIDE_B,
    ]);

    $events = feed($viewer, FeedService::FILTER_RESULTS);

    expect($events)->toHaveCount(1)
        ->and($events->first()->type)->toBe(FeedEvent::TYPE_LOSE);
});

test('tie battle (no winning side) produces no result event', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $viewer->following()->attach($author->id);

    $battle = Battle::factory()->create([
        'status' => Battle::STATUS_SETTLED,
        'settled_at' => now(),
        'winning_side' => null,
    ]);
    Vote::factory()->create(['user_id' => $author->id, 'battle_id' => $battle->id, 'side' => Battle::SIDE_A]);

    expect(feed($viewer, FeedService::FILTER_RESULTS))->toHaveCount(0);
});

test('multiple votes in the same battle collapse to one result event', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $viewer->following()->attach($author->id);

    $battle = Battle::factory()->settled(Battle::SIDE_A)->create();
    Vote::factory()->count(3)->create([
        'user_id' => $author->id,
        'battle_id' => $battle->id,
        'side' => Battle::SIDE_A,
    ]);

    expect(feed($viewer, FeedService::FILTER_RESULTS))->toHaveCount(1);
});

test('activity by a non-followed user is excluded when following someone', function () {
    $viewer = User::factory()->create();
    $followed = User::factory()->create();
    $stranger = User::factory()->create();
    $viewer->following()->attach($followed->id);

    Battle::factory()->create(['created_by_id' => $stranger->id]);

    expect(feed($viewer))->toHaveCount(0);
});

test('global fallback shows other users activity when following nobody', function () {
    $viewer = User::factory()->create();
    $stranger = User::factory()->create();
    Battle::factory()->create(['created_by_id' => $stranger->id]);

    $events = feed($viewer);

    expect($events)->toHaveCount(1)
        ->and($events->first()->type)->toBe(FeedEvent::TYPE_CREATE);
});

test('global fallback excludes the viewer own activity', function () {
    $viewer = User::factory()->create();
    Battle::factory()->create(['created_by_id' => $viewer->id]);

    expect(feed($viewer))->toHaveCount(0);
});

test('votes filter returns only vote events', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $viewer->following()->attach($author->id);

    Battle::factory()->create(['created_by_id' => $author->id]);
    Vote::factory()->create(['user_id' => $author->id]);

    $events = feed($viewer, FeedService::FILTER_VOTES);

    expect($events)->toHaveCount(1)
        ->and($events->first()->type)->toBe(FeedEvent::TYPE_VOTE);
});

test('results are ordered by recency even past the over-fetch window', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $viewer->following()->attach($author->id);

    // limit = 2 → over-fetch window = limit * 5 = 10. Create 10 older settled
    // battles (lower primary keys) then one freshly-settled battle (highest key).
    // Without recency ordering on the over-fetch, the newest would be dropped.
    foreach (range(1, 10) as $i) {
        $old = Battle::factory()->create([
            'status' => Battle::STATUS_SETTLED,
            'winning_side' => Battle::SIDE_A,
            'settled_at' => now()->subDays(10 + $i),
        ]);
        Vote::factory()->create(['user_id' => $author->id, 'battle_id' => $old->id, 'side' => Battle::SIDE_A]);
    }

    $newest = Battle::factory()->create([
        'status' => Battle::STATUS_SETTLED,
        'winning_side' => Battle::SIDE_A,
        'settled_at' => now(),
    ]);
    Vote::factory()->create(['user_id' => $author->id, 'battle_id' => $newest->id, 'side' => Battle::SIDE_A]);

    $events = app(FeedService::class)
        ->events($viewer, FeedService::FILTER_RESULTS, null, 2);

    expect($events->first()->battle->id)->toBe($newest->id)
        ->and($events->first()->type)->toBe(FeedEvent::TYPE_WIN);
});

test('vote and argue in the same battle merge into one card', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $viewer->following()->attach($author->id);

    $battle = Battle::factory()->create();
    Vote::factory()->create(['user_id' => $author->id, 'battle_id' => $battle->id]);
    Comment::factory()->create(['user_id' => $author->id, 'battle_id' => $battle->id, 'body' => 'Because reasons']);

    $events = feed($viewer);

    expect($events)->toHaveCount(1)
        ->and($events->first()->type)->toBe(FeedEvent::TYPE_VOTE_ARGUE)
        ->and($events->first()->argumentText)->toBe('Because reasons');
});

test('vote and argue in different battles stay separate', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $viewer->following()->attach($author->id);

    Vote::factory()->create(['user_id' => $author->id]);
    Comment::factory()->create(['user_id' => $author->id]);

    $events = feed($viewer);

    expect($events)->toHaveCount(2)
        ->and($events->pluck('type')->sort()->values()->all())
        ->toBe([FeedEvent::TYPE_ARGUE, FeedEvent::TYPE_VOTE]);
});

test('create event never merges with a vote in the same battle', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $viewer->following()->attach($author->id);

    $battle = Battle::factory()->create(['created_by_id' => $author->id]);
    Vote::factory()->create(['user_id' => $author->id, 'battle_id' => $battle->id]);

    $events = feed($viewer);

    expect($events)->toHaveCount(2)
        ->and($events->pluck('type')->sort()->values()->all())
        ->toBe([FeedEvent::TYPE_CREATE, FeedEvent::TYPE_VOTE]);
});

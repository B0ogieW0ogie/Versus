<?php

use App\Livewire\BattleShow;
use App\Models\Battle;
use App\Models\Comment;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function assertHtmlOrder(string $html, string $first, string $second): void
{
    $p1 = strpos($html, $first);
    $p2 = strpos($html, $second);
    expect($p1)->not->toBeFalse();
    expect($p2)->not->toBeFalse();
    expect($p1)->toBeLessThan($p2);
}

test('battle comments default to popular sort by author side stake sum', function () {
    $battle = Battle::factory()->create();
    $uLow = User::factory()->create(['balance' => 5000]);
    $uHigh = User::factory()->create(['balance' => 5000]);
    $uMid = User::factory()->create(['balance' => 5000]);

    Vote::factory()->create(['user_id' => $uLow->id, 'battle_id' => $battle->id, 'side' => 'A', 'amount' => 5, 'weight' => 5]);
    Vote::factory()->create(['user_id' => $uHigh->id, 'battle_id' => $battle->id, 'side' => 'B', 'amount' => 100, 'weight' => 100]);
    Vote::factory()->create(['user_id' => $uMid->id, 'battle_id' => $battle->id, 'side' => 'A', 'amount' => 10, 'weight' => 10]);

    Comment::factory()->for($battle)->for($uLow)->create([
        'body' => 'COMMENT_SORT_LOW',
        'side' => 'A',
        'created_at' => now()->subHours(3),
    ]);
    Comment::factory()->for($battle)->for($uHigh)->create([
        'body' => 'COMMENT_SORT_HIGH',
        'side' => 'B',
        'created_at' => now()->subHours(2),
    ]);
    Comment::factory()->for($battle)->for($uMid)->create([
        'body' => 'COMMENT_SORT_MID',
        'side' => 'A',
        'created_at' => now()->subHour(),
    ]);

    $html = Livewire::actingAs($uLow)
        ->test(BattleShow::class, ['battle' => $battle])
        ->assertSet('commentSort', 'popular')
        ->html();

    assertHtmlOrder($html, 'COMMENT_SORT_HIGH', 'COMMENT_SORT_MID');
    assertHtmlOrder($html, 'COMMENT_SORT_MID', 'COMMENT_SORT_LOW');
});

test('battle comments new sort orders by created at descending', function () {
    $battle = Battle::factory()->create();
    $uLow = User::factory()->create(['balance' => 5000]);
    $uHigh = User::factory()->create(['balance' => 5000]);
    $uMid = User::factory()->create(['balance' => 5000]);

    Vote::factory()->create(['user_id' => $uLow->id, 'battle_id' => $battle->id, 'side' => 'A', 'amount' => 5, 'weight' => 5]);
    Vote::factory()->create(['user_id' => $uHigh->id, 'battle_id' => $battle->id, 'side' => 'B', 'amount' => 100, 'weight' => 100]);
    Vote::factory()->create(['user_id' => $uMid->id, 'battle_id' => $battle->id, 'side' => 'A', 'amount' => 10, 'weight' => 10]);

    Comment::factory()->for($battle)->for($uLow)->create([
        'body' => 'COMMENT_NEW_OLDEST',
        'side' => 'A',
        'created_at' => now()->subHours(3),
    ]);
    Comment::factory()->for($battle)->for($uHigh)->create([
        'body' => 'COMMENT_NEW_MIDDLE',
        'side' => 'B',
        'created_at' => now()->subHours(2),
    ]);
    Comment::factory()->for($battle)->for($uMid)->create([
        'body' => 'COMMENT_NEW_NEWEST',
        'side' => 'A',
        'created_at' => now()->subMinutes(5),
    ]);

    $html = Livewire::actingAs($uLow)
        ->test(BattleShow::class, ['battle' => $battle])
        ->set('commentSort', 'new')
        ->assertSet('commentSort', 'new')
        ->html();

    assertHtmlOrder($html, 'COMMENT_NEW_NEWEST', 'COMMENT_NEW_MIDDLE');
    assertHtmlOrder($html, 'COMMENT_NEW_MIDDLE', 'COMMENT_NEW_OLDEST');
});

test('invalid comment sort falls back to popular', function () {
    $battle = Battle::factory()->create();
    $user = User::factory()->create(['balance' => 100]);

    Livewire::actingAs($user)
        ->test(BattleShow::class, ['battle' => $battle])
        ->set('commentSort', 'bogus')
        ->assertSet('commentSort', 'popular');
});

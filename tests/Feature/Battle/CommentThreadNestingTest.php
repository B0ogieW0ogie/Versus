<?php

use App\Livewire\CommentThread;
use App\Models\Battle;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('deep thread replies render in a single visual indent under root', function () {
    $battle = Battle::factory()->create();
    $users = User::factory()->count(4)->create(['balance' => 100]);

    $root = Comment::factory()->for($battle)->for($users[0])->create([
        'body' => 'THREAD_ROOT',
    ]);
    $level1 = Comment::factory()->for($battle)->for($users[1])->create([
        'body' => 'THREAD_L1',
        'parent_id' => $root->id,
        'reply_to_user_id' => $users[0]->id,
    ]);
    $level2 = Comment::factory()->for($battle)->for($users[2])->create([
        'body' => 'THREAD_L2',
        'parent_id' => $level1->id,
        'reply_to_user_id' => $users[1]->id,
    ]);
    Comment::factory()->for($battle)->for($users[3])->create([
        'body' => 'THREAD_L3',
        'parent_id' => $level2->id,
        'reply_to_user_id' => $users[2]->id,
    ]);

    $html = Livewire::actingAs($users[0])
        ->test(CommentThread::class, ['battle' => $battle])
        ->html();

    expect($html)->toContain('THREAD_ROOT')
        ->and($html)->toContain('THREAD_L1')
        ->and($html)->toContain('THREAD_L2')
        ->and($html)->toContain('THREAD_L3');

    preg_match_all('/<div class="ml-11 border-l border-white\/5 pl-3">/', $html, $wrappers);
    expect($wrappers[0])->toHaveCount(1);
});

test('support argument button appears only on root comments with a side', function () {
    $battle = Battle::factory()->create();
    $users = User::factory()->count(2)->create(['balance' => 100]);

    $root = Comment::factory()->for($battle)->for($users[0])->create([
        'body' => 'ROOT_WITH_SIDE',
        'side' => 'A',
    ]);
    Comment::factory()->for($battle)->for($users[1])->create([
        'body' => 'REPLY_WITH_SIDE',
        'side' => 'A',
        'parent_id' => $root->id,
        'reply_to_user_id' => $users[0]->id,
    ]);

    $label = __('comments.support_argument');

    $html = Livewire::actingAs($users[0])
        ->test(CommentThread::class, ['battle' => $battle])
        ->html();

    expect(substr_count($html, $label))->toBe(1);
});

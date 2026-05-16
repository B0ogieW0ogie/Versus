<?php

use App\Livewire\CommentThread;
use App\Models\Battle;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('author can delete own comment and sees deleted placeholder', function () {
    $battle = Battle::factory()->create();
    $author = User::factory()->create(['balance' => 100]);
    $other = User::factory()->create(['balance' => 100]);

    $comment = Comment::factory()->for($battle)->for($author)->create([
        'body' => 'SECRET_ARGUMENT',
    ]);

    Livewire::actingAs($author)
        ->test(CommentThread::class, ['battle' => $battle])
        ->call('deleteComment', $comment->id)
        ->assertSee(__('comments.deleted'))
        ->assertDontSee('SECRET_ARGUMENT');

    expect($comment->fresh()->trashed())->toBeTrue();
});

test('user cannot delete another users comment', function () {
    $battle = Battle::factory()->create();
    $author = User::factory()->create(['balance' => 100]);
    $other = User::factory()->create(['balance' => 100]);

    $comment = Comment::factory()->for($battle)->for($author)->create([
        'body' => 'KEEP_ME',
    ]);

    Livewire::actingAs($other)
        ->test(CommentThread::class, ['battle' => $battle])
        ->call('deleteComment', $comment->id)
        ->assertSee('KEEP_ME')
        ->assertDontSee(__('comments.deleted'));

    expect($comment->fresh()->trashed())->toBeFalse();
});

test('delete button is shown only to comment author', function () {
    $battle = Battle::factory()->create();
    $author = User::factory()->create(['balance' => 100]);
    $other = User::factory()->create(['balance' => 100]);

    Comment::factory()->for($battle)->for($author)->create(['body' => 'Mine']);

    $authorHtml = Livewire::actingAs($author)
        ->test(CommentThread::class, ['battle' => $battle])
        ->html();

    $otherHtml = Livewire::actingAs($other)
        ->test(CommentThread::class, ['battle' => $battle])
        ->html();

    expect($authorHtml)->toContain(__('comments.delete'))
        ->and($otherHtml)->not->toContain(__('comments.delete'));
});

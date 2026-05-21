<?php

use App\Livewire\CommentThread;
use App\Models\Battle;
use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('like sends one token and creates permanent like record', function () {
    $battle = Battle::factory()->create();
    $voter = User::factory()->create(['balance' => 100]);
    $author = User::factory()->create();

    $comment = Comment::factory()->for($battle)->for($author)->create([
        'side' => 'A',
        'body' => 'Like me',
    ]);

    Livewire::actingAs($voter)
        ->test(CommentThread::class, ['battle' => $battle])
        ->call('likeComment', $comment->id)
        ->assertHasNoErrors('vote')
        ->assertDispatched('battle-voted')
        ->assertDispatched('balance-updated');

    expect((float) $voter->fresh()->balance)->toBe(99.0);
    expect((float) $battle->fresh()->total_pool)->toBe(1.0);
    expect(CommentLike::query()->where('user_id', $voter->id)->where('comment_id', $comment->id)->exists())->toBeTrue();
    expect(Vote::query()->where('user_id', $voter->id)->where('amount', 1)->count())->toBe(1);
});

test('second like shows already liked toast and does not charge again', function () {
    $battle = Battle::factory()->create();
    $voter = User::factory()->create(['balance' => 100]);
    $author = User::factory()->create();

    $comment = Comment::factory()->for($battle)->for($author)->create([
        'side' => 'B',
        'body' => 'Liked once',
    ]);

    $component = Livewire::actingAs($voter)
        ->test(CommentThread::class, ['battle' => $battle])
        ->call('likeComment', $comment->id);

    $component
        ->call('likeComment', $comment->id)
        ->assertDispatched('versus-stake-toast', title: __('comments.already_liked'));

    expect((float) $voter->fresh()->balance)->toBe(99.0);
    expect(CommentLike::query()->where('comment_id', $comment->id)->count())->toBe(1);
});

test('support comment stakes chosen amount on comment side', function () {
    $battle = Battle::factory()->create();
    $voter = User::factory()->create(['balance' => 500]);
    $author = User::factory()->create();

    $comment = Comment::factory()->for($battle)->for($author)->create([
        'side' => 'A',
        'body' => 'Support me',
    ]);

    Livewire::actingAs($voter)
        ->test(CommentThread::class, ['battle' => $battle])
        ->call('supportComment', $comment->id, 150)
        ->assertHasNoErrors('vote')
        ->assertDispatched('versus-stake-toast', title: str_replace('__N__', '150', __('battle.stake_modal_title')));

    expect((float) $voter->fresh()->balance)->toBe(350.0);
    expect((float) $battle->fresh()->total_pool)->toBe(150.0);
    expect(Vote::query()->where('user_id', $voter->id)->where('side', 'A')->value('amount'))->toBe('150.00');
    expect(CommentLike::query()->where('comment_id', $comment->id)->where('user_id', $voter->id)->exists())->toBeFalse();
});

test('support comment records vote stake transaction', function () {
    $battle = Battle::factory()->create();
    $voter = User::factory()->create(['balance' => 200]);
    $comment = Comment::factory()->for($battle)->create(['side' => 'B', 'body' => 'x']);

    Livewire::actingAs($voter)
        ->test(CommentThread::class, ['battle' => $battle])
        ->call('supportComment', $comment->id, 50);

    $this->assertDatabaseHas('transactions', [
        'user_id' => $voter->id,
        'battle_id' => $battle->id,
        'type' => Transaction::TYPE_VOTE_STAKE,
        'amount' => '-50.00',
    ]);
});

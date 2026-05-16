<?php

namespace App\Livewire;

use App\Actions\Battles\CastVoteAction;
use App\Actions\Comments\DeleteCommentAction;
use App\Actions\Comments\PostCommentAction;
use App\Actions\Comments\ToggleCommentLikeAction;
use App\Models\Battle;
use App\Models\Comment;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class CommentThread extends Component
{
    public Battle $battle;

    public string $commentSort = 'popular';

    public string $commentBody = '';

    public ?string $commentSide = null;

    public ?int $replyingToCommentId = null;

    public ?int $replyToUserId = null;

    public string $replyToUserName = '';

    #[On('battle-voted')]
    public function refreshBattle(): void
    {
        $this->battle->refresh();
    }

    public function supportFor(int $commentId, CastVoteAction $action): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login');

            return;
        }

        $comment = $this->battle->comments()->withTrashed()->find($commentId);
        if ($comment === null || $comment->trashed() || ! in_array($comment->side, [Battle::SIDE_A, Battle::SIDE_B], true)) {
            return;
        }

        try {
            $action(Auth::user(), $this->battle, $comment->side, 1.0);
            $this->battle->refresh();
            Auth::user()?->refresh();
            $this->dispatch('battle-voted');
            $this->dispatch('balance-updated', balance: (int) Auth::user()->balance);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->addError('vote', $message);
                }
            }
        }
    }

    public function startReply(int $commentId): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login');

            return;
        }

        $comment = $this->battle->comments()->withTrashed()->with('user:id,name')->find($commentId);
        if ($comment === null || $comment->trashed()) {
            return;
        }

        $this->replyingToCommentId = $comment->id;
        $this->replyToUserId = $comment->user_id;
        $this->replyToUserName = $comment->user->name;
        $this->commentBody = $comment->user->name.', ';
        $this->resetErrorBag('commentBody');
    }

    public function cancelReply(): void
    {
        $this->replyingToCommentId = null;
        $this->replyToUserId = null;
        $this->replyToUserName = '';
        $this->commentBody = '';
    }

    public function comment(PostCommentAction $action): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login');

            return;
        }

        $this->validate([
            'commentBody' => ['required', 'string', 'max:500'],
            'commentSide' => ['nullable', 'in:A,B'],
        ]);

        $parent = null;
        $replyTo = null;

        if ($this->replyingToCommentId !== null) {
            $parent = $this->battle->comments()->find($this->replyingToCommentId);
            if ($parent === null) {
                $this->addError('commentBody', __('comments.invalid_parent'));

                return;
            }

            if ($this->replyToUserId !== null) {
                $replyTo = User::query()->find($this->replyToUserId);
            }
        }

        try {
            $action(
                Auth::user(),
                $this->battle,
                $this->commentBody,
                $this->replyingToCommentId === null ? $this->commentSide : null,
                $parent,
                $replyTo,
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError(
                        $field === 'body' ? 'commentBody' : $field,
                        $message,
                    );
                }
            }

            return;
        }

        $this->commentBody = '';
        $this->commentSide = null;
        $this->cancelReply();
    }

    public function toggleLike(int $commentId, ToggleCommentLikeAction $action): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login');

            return;
        }

        $comment = $this->battle->comments()->withTrashed()->find($commentId);
        if ($comment === null || $comment->trashed()) {
            return;
        }

        $action(Auth::user(), $comment);
    }

    public function deleteComment(int $commentId, DeleteCommentAction $action): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login');

            return;
        }

        $comment = $this->battle->comments()->withTrashed()->find($commentId);
        if ($comment === null) {
            return;
        }

        if ($this->replyingToCommentId === $commentId) {
            $this->cancelReply();
        }

        try {
            $action(Auth::user(), $comment);
        } catch (ValidationException) {
            return;
        }
    }

    public function reportComment(int $commentId): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login');

            return;
        }

        if ($this->battle->comments()->whereKey($commentId)->doesntExist()) {
            return;
        }

        // Stub: reporting will be implemented later.
    }

    public function updatedCommentSort(string $value): void
    {
        if (! in_array($value, ['popular', 'new'], true)) {
            $this->commentSort = 'popular';
        }
    }

    /**
     * @return SupportCollection<int, Comment>
     */
    private function rootComments(): SupportCollection
    {
        $userId = Auth::id();

        $all = Comment::query()
            ->withTrashed()
            ->where('battle_id', $this->battle->id)
            ->with([
                'user:id,name,avatar_path',
                'replyToUser:id,name',
            ])
            ->withCount('likes')
            ->when($userId !== null, fn ($q) => $q->withExists([
                'likes as liked_by_user' => fn ($q) => $q->where('user_id', $userId),
            ]))
            ->select('comments.*')
            ->addSelect([
                'author_side_votes_sum' => Vote::query()
                    ->selectRaw('COALESCE(SUM(amount), 0)')
                    ->whereColumn('votes.user_id', 'comments.user_id')
                    ->whereColumn('votes.battle_id', 'comments.battle_id')
                    ->whereColumn('votes.side', 'comments.side'),
            ])
            ->get();

        $tree = Comment::buildTree($all);

        if ($this->commentSort === 'new') {
            return $tree->sortByDesc('created_at')->values();
        }

        return $tree
            ->sortByDesc(fn (Comment $c) => [(float) $c->author_side_votes_sum, $c->created_at?->timestamp ?? 0])
            ->values();
    }

    public function render(): View
    {
        return view('livewire.comment-thread', [
            'rootComments' => $this->rootComments(),
        ]);
    }
}

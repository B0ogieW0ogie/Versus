<?php

namespace App\Livewire;

use App\Actions\Battles\CastVoteAction;
use App\Actions\Comments\DeleteCommentAction;
use App\Actions\Comments\LikeCommentAction;
use App\Actions\Comments\PostCommentAction;
use App\Models\Battle;
use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\User;
use App\Models\Vote;
use App\Support\BattleStakeLimit;
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

    /** @var array<int, int> */
    public array $expandedThreads = [];

    #[On('battle-voted')]
    public function refreshBattle(): void
    {
        $this->battle->refresh();
    }

    public function supportComment(int $commentId, int $amount, CastVoteAction $action): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login');

            return;
        }

        $comment = $this->resolveCommentForStake($commentId);
        if ($comment === null) {
            return;
        }

        try {
            $action(Auth::user(), $this->battle, $comment->side, (float) $amount);
            $this->afterStakeSuccess((int) $amount);
        } catch (ValidationException $e) {
            $this->surfaceVoteErrors($e);
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

        if ($parent !== null) {
            $this->expandThread($this->rootCommentIdFor($parent));
        }

        $this->cancelReply();
    }

    public function toggleThread(int $rootCommentId): void
    {
        if ($this->isThreadExpanded($rootCommentId)) {
            $this->expandedThreads = array_values(array_filter(
                $this->expandedThreads,
                fn (int $id): bool => $id !== $rootCommentId,
            ));
        } else {
            $this->expandedThreads[] = $rootCommentId;
        }
    }

    public function isThreadExpanded(int $rootCommentId): bool
    {
        return in_array($rootCommentId, $this->expandedThreads, true);
    }

    public function likeComment(int $commentId, LikeCommentAction $action): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login');

            return;
        }

        $comment = $this->battle->comments()->withTrashed()->find($commentId);
        if ($comment === null || $comment->trashed()) {
            return;
        }

        try {
            $result = $action(Auth::user(), $comment, $this->battle);

            if ($result['already_liked']) {
                $this->dispatch(
                    'versus-stake-toast',
                    title: __('comments.already_liked'),
                    body: '',
                    battleId: $this->battle->id,
                );

                return;
            }

            $this->afterStakeSuccess(1);
        } catch (ValidationException $e) {
            $this->surfaceVoteErrors($e);
        }
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

    public function setCommentSort(string $sort): void
    {
        $this->commentSort = in_array($sort, ['popular', 'new'], true) ? $sort : 'popular';
    }

    public function updatedCommentSort(string $value): void
    {
        $this->setCommentSort($value);
    }

    private function expandThread(int $rootCommentId): void
    {
        if (! $this->isThreadExpanded($rootCommentId)) {
            $this->expandedThreads[] = $rootCommentId;
        }
    }

    private function rootCommentIdFor(Comment $comment): int
    {
        $current = $comment;

        while ($current->parent_id !== null) {
            $parent = Comment::query()
                ->withTrashed()
                ->where('battle_id', $this->battle->id)
                ->find($current->parent_id);

            if ($parent === null) {
                break;
            }

            $current = $parent;
        }

        return $current->id;
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
            ->select('comments.*')
            ->addSelect([
                'author_side_votes_sum' => Vote::query()
                    ->selectRaw('COALESCE(SUM(amount), 0)')
                    ->whereColumn('votes.user_id', 'comments.user_id')
                    ->whereColumn('votes.battle_id', 'comments.battle_id')
                    ->whereColumn('votes.side', 'comments.side'),
            ])
            ->get();

        if ($userId !== null) {
            $likedCommentIds = CommentLike::query()
                ->where('user_id', $userId)
                ->whereIn('comment_id', $all->pluck('id'))
                ->pluck('comment_id');

            foreach ($all as $comment) {
                $comment->setAttribute('liked_by_user', $likedCommentIds->contains($comment->id));
            }
        }

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
        $user = Auth::user();
        $userBalance = $user !== null ? (float) $user->balance : 0.0;
        $maxVoteAmount = (int) config('versus.max_vote_amount');
        $remainingBattleStake = $user !== null
            ? (int) BattleStakeLimit::remainingForUser($user, $this->battle)
            : 0;
        $maxAllowed = (int) min((int) $userBalance, $maxVoteAmount, $remainingBattleStake);

        return view('livewire.comment-thread', [
            'rootComments' => $this->rootComments(),
            'maxAllowed' => $maxAllowed,
            'maxVoteAmount' => $maxVoteAmount,
            'userBalance' => $userBalance,
            'canStake' => $user !== null
                && $this->battle->isOpenForVoting()
                && $userBalance >= 1
                && $remainingBattleStake >= 1,
        ]);
    }

    private function resolveCommentForStake(int $commentId): ?Comment
    {
        $comment = $this->battle->comments()->withTrashed()->find($commentId);
        if ($comment === null || $comment->trashed() || ! in_array($comment->side, [Battle::SIDE_A, Battle::SIDE_B], true)) {
            return null;
        }

        return $comment;
    }

    private function afterStakeSuccess(int $amount): void
    {
        $this->battle->refresh();
        Auth::user()?->refresh();
        $this->dispatch(
            'versus-stake-toast',
            title: str_replace('__N__', (string) $amount, __('battle.stake_modal_title')),
            body: __('battle.stake_modal_body'),
            battleId: $this->battle->id,
            poolTotal: (float) $this->battle->total_pool,
        );
        $this->dispatch('balance-updated', balance: (int) Auth::user()->balance);
        $this->dispatch('battle-voted');
    }

    private function surfaceVoteErrors(ValidationException $e): void
    {
        foreach ($e->errors() as $messages) {
            foreach ($messages as $message) {
                $this->addError('vote', $message);
            }
        }
    }
}

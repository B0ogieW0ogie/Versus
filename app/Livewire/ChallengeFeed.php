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

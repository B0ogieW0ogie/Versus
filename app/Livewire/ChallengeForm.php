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

    /** Computed once in mount(): recomputing per render changed x-data and re-initialised Alpine mid-upload. */
    #[Locked]
    public string $backUrl = '';

    public string $uploadId = '';

    public string $title = '';

    public string $rules = '';

    public string $category = '';

    public string $duration = '';

    public string $format = Challenge::FORMAT_PUBLIC;

    public ?int $opponentId = null;

    public string $opponentQuery = '';

    public string $username = '';

    public function mount(?Challenge $challenge = null): void
    {
        $fallback = route('challenges.index');
        $previous = url()->previous($fallback);
        $this->backUrl = $previous !== url()->current() ? $previous : $fallback;

        if ($challenge !== null && $challenge->exists) {
            if (! in_array($challenge->status, [Challenge::STATUS_ACTIVE, Challenge::STATUS_CLOSED], true)) {
                abort(404);
            }

            /** @var User $user */
            $user = Auth::user();
            if ($challenge->isDuel() && ! $challenge->allowsResponseFrom($user)) {
                abort(403);
            }

            $this->challengeId = $challenge->id;
            $this->title = $challenge->title;
            $this->rules = $challenge->rules;

            return;
        }

        $this->duration = (string) config('versus.challenges.default_duration');

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
                $this->category = $failed->category ?? '';
                if (array_key_exists($failed->duration, (array) config('versus.challenges.durations'))) {
                    $this->duration = $failed->duration;
                }
                $this->format = $failed->format;
                $this->opponentId = $failed->opponent_id;
            }
        }
    }

    public function updatedFormat(): void
    {
        if ($this->format !== Challenge::FORMAT_DUEL) {
            $this->clearOpponent();
        }
    }

    public function selectOpponent(int $userId): void
    {
        if ($userId !== Auth::id() && User::whereKey($userId)->exists()) {
            $this->opponentId = $userId;
            $this->opponentQuery = '';
            $this->resetErrorBag('opponent');
        }
    }

    public function clearOpponent(): void
    {
        $this->opponentId = null;
        $this->opponentQuery = '';
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
                    $this->category,
                    $this->duration,
                    $this->format,
                    $this->opponentId !== null ? User::find($this->opponentId) : null,
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
            'categories' => Challenge::CATEGORIES,
            'opponent' => $this->opponentId !== null ? User::find($this->opponentId) : null,
            'opponentResults' => $this->opponentResults($user),
            'maxSeconds' => (int) config('versus.challenges.max_video_seconds'),
            'maxBytes' => (int) config('versus.challenges.max_upload_mb') * 1024 * 1024,
        ]);
    }

    /** @return list<User> */
    private function opponentResults(User $user): array
    {
        $query = mb_strtolower(ltrim(trim($this->opponentQuery), '@'));
        if ($this->format !== Challenge::FORMAT_DUEL || $this->opponentId !== null || mb_strlen($query) < 2) {
            return [];
        }

        // `_` (valid in usernames) stays a one-char wildcard: harmless, it only widens the match.
        $like = '%'.str_replace(['%', '\\'], '', $query).'%';

        // LOWER() + LIKE instead of ILIKE: tests run on SQLite.
        return User::query()
            ->whereKeyNot($user->id)
            ->where(fn ($q) => $q->whereRaw('LOWER(username) LIKE ?', [$like])->orWhereRaw('LOWER(name) LIKE ?', [$like]))
            ->orderByRaw('CASE WHEN LOWER(username) = ? THEN 0 ELSE 1 END', [$query])
            ->orderBy('username')
            ->limit(6)
            ->get()
            ->all();
    }
}

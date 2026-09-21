<?php

namespace App\Actions\Challenges;

use App\Jobs\ProcessEntryVideo;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\User;
use App\Services\Video\Trim;
use App\Services\Video\UploadStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateChallengeAction
{
    public function __construct(private readonly UploadStore $uploads) {}

    public function __invoke(
        User $user,
        string $uploadId,
        string $title,
        string $rules,
        string $category,
        string $duration,
        string $format = Challenge::FORMAT_PUBLIC,
        ?User $opponent = null,
        ?string $username = null,
        ?Challenge $replacing = null,
        ?Trim $trim = null,
    ): Challenge {
        $title = trim($title);
        $rules = trim($rules);
        $username = $username !== null ? trim($username) : null;
        // A Public challenge never carries an opponent, whatever the form sent.
        $opponent = $format === Challenge::FORMAT_DUEL ? $opponent : null;

        $this->validate($user, $title, $rules, $category, $duration, $format, $opponent, $username, $replacing);

        $source = $this->uploads->claim($user, $uploadId);

        $challenge = DB::transaction(function () use ($user, $title, $rules, $category, $duration, $format, $opponent, $username, $replacing, $source, $trim): Challenge {
            if ($user->username === null && $username !== null) {
                $user->forceFill(['username' => $username])->save();
            }

            if ($replacing !== null) {
                Challenge::whereKey($replacing->id)->lockForUpdate()->firstOrFail()->delete();
            }

            $challenge = Challenge::create([
                'slug' => (Str::slug($title) ?: 'challenge').'-'.Str::lower(Str::random(6)),
                'user_id' => $user->id,
                'title' => $title,
                'rules' => $rules,
                'category' => $category,
                'format' => $format,
                'opponent_id' => $opponent?->id,
                'duration' => $duration,
                'status' => Challenge::STATUS_PROCESSING,
            ]);

            $entry = ChallengeEntry::create([
                'challenge_id' => $challenge->id,
                'user_id' => $user->id,
                'is_original' => true,
                'status' => ChallengeEntry::STATUS_PROCESSING,
                'submitted_at' => now(),
                'trim_start_ms' => $trim?->startMs,
                'trim_end_ms' => $trim?->endMs,
            ]);

            $destination = "entries/{$entry->id}/source";
            Storage::disk('local')->move($source, $destination);
            $entry->update(['source_path' => $destination]);

            ProcessEntryVideo::dispatch($entry->id)->afterCommit();

            return $challenge;
        });

        $this->uploads->discard($uploadId);

        return $challenge;
    }

    private function validate(
        User $user,
        string $title,
        string $rules,
        string $category,
        string $duration,
        string $format,
        ?User $opponent,
        ?string $username,
        ?Challenge $replacing,
    ): void {
        $errors = [];

        if ($title === '' || mb_strlen($title) > 100) {
            $errors['title'] = __('challenges.title_required');
        }
        if ($rules === '' || mb_strlen($rules) > 500) {
            $errors['rules'] = __('challenges.rules_required');
        }
        if (! in_array($category, Challenge::CATEGORIES, true)) {
            $errors['category'] = __('challenges.category_required');
        }
        if (! array_key_exists($duration, (array) config('versus.challenges.durations'))) {
            $errors['duration'] = __('challenges.duration_required');
        }
        if (! in_array($format, Challenge::FORMATS, true)) {
            $errors['format'] = __('challenges.format_required');
        } elseif ($format === Challenge::FORMAT_DUEL && $opponent === null) {
            $errors['opponent'] = __('challenges.opponent_required');
        } elseif ($opponent !== null && $opponent->id === $user->id) {
            $errors['opponent'] = __('challenges.opponent_self');
        }

        if ($user->username === null) {
            if ($username === null || preg_match('/^[a-zA-Z0-9_]{3,32}$/', $username) !== 1) {
                $errors['username'] = __('challenges.username_required');
            } elseif (User::where('username', $username)->exists()) {
                $errors['username'] = __('challenges.username_taken');
            }
        }

        if ($replacing !== null && ($replacing->user_id !== $user->id || $replacing->status !== Challenge::STATUS_FAILED)) {
            $errors['challenge'] = __('challenges.cannot_retry');
        }

        $limit = (int) config('versus.challenges.daily_create_limit');
        $createdToday = Challenge::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', Challenge::STATUS_FAILED)
            ->where('created_at', '>=', now()->subDay())
            ->count();
        if ($createdToday >= $limit) {
            $errors['daily_limit'] = __('challenges.daily_limit', ['limit' => $limit]);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}

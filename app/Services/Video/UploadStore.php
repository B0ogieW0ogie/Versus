<?php

namespace App\Services\Video;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Chunked uploads on the private `local` disk. Metadata lives in the cache for
 * 24 h; the daily cleanup command removes directories left behind.
 */
class UploadStore
{
    private const TTL_SECONDS = 86400;

    public function start(User $user): string
    {
        $id = (string) Str::uuid();

        Storage::disk('local')->makeDirectory($this->dir($id));
        $this->put($id, ['user_id' => $user->id, 'size' => 0, 'next_chunk' => 0, 'completed' => false]);

        return $id;
    }

    public function appendChunk(User $user, string $uploadId, int $index, UploadedFile $chunk): void
    {
        $meta = $this->ownedMeta($user, $uploadId);

        if ($meta['completed']) {
            throw ValidationException::withMessages(['upload' => __('challenges.invalid_upload')]);
        }

        if ($index < $meta['next_chunk']) {
            return; // Retried chunk that already arrived.
        }

        if ($index !== $meta['next_chunk']) {
            throw ValidationException::withMessages(['chunk' => __('challenges.upload_failed')]);
        }

        $size = $meta['size'] + (int) $chunk->getSize();
        if ($size > $this->maxBytes()) {
            $this->discard($uploadId);

            throw ValidationException::withMessages([
                'upload' => __('challenges.video_too_big', ['mb' => config('versus.challenges.max_upload_mb')]),
            ]);
        }

        $in = fopen((string) $chunk->getRealPath(), 'rb');
        $out = fopen(Storage::disk('local')->path($this->sourcePath($uploadId)), 'ab');
        if ($in === false || $out === false) {
            throw ValidationException::withMessages(['chunk' => __('challenges.upload_failed')]);
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        $meta['size'] = $size;
        $meta['next_chunk'] = $index + 1;
        $this->put($uploadId, $meta);
    }

    /** @return array{next_chunk: int, size: int, completed: bool} */
    public function status(User $user, string $uploadId): array
    {
        $meta = $this->ownedMeta($user, $uploadId);

        return ['next_chunk' => $meta['next_chunk'], 'size' => $meta['size'], 'completed' => $meta['completed']];
    }

    public function complete(User $user, string $uploadId): void
    {
        $meta = $this->ownedMeta($user, $uploadId);

        if ($meta['size'] === 0) {
            throw ValidationException::withMessages(['upload' => __('challenges.invalid_upload')]);
        }

        $meta['completed'] = true;
        $this->put($uploadId, $meta);
    }

    public function claim(User $user, string $uploadId): string
    {
        $meta = $this->ownedMeta($user, $uploadId);

        if (! $meta['completed']) {
            throw ValidationException::withMessages(['upload' => __('challenges.invalid_upload')]);
        }

        Cache::forget($this->key($uploadId));

        return $this->sourcePath($uploadId);
    }

    public function discard(string $uploadId): void
    {
        if (! Str::isUuid($uploadId)) {
            return;
        }

        Cache::forget($this->key($uploadId));
        Storage::disk('local')->deleteDirectory($this->dir($uploadId));
    }

    public function dir(string $uploadId): string
    {
        return 'uploads/'.$uploadId;
    }

    public function chunkBytes(): int
    {
        return (int) config('versus.challenges.upload_chunk_mb') * 1024 * 1024;
    }

    private function sourcePath(string $uploadId): string
    {
        return $this->dir($uploadId).'/source';
    }

    private function maxBytes(): int
    {
        return (int) config('versus.challenges.max_upload_mb') * 1024 * 1024;
    }

    private function key(string $uploadId): string
    {
        return 'challenge-upload:'.$uploadId;
    }

    /** @param array{user_id: int, size: int, next_chunk: int, completed: bool} $meta */
    private function put(string $uploadId, array $meta): void
    {
        Cache::put($this->key($uploadId), $meta, self::TTL_SECONDS);
    }

    /** @return array{user_id: int, size: int, next_chunk: int, completed: bool} */
    private function ownedMeta(User $user, string $uploadId): array
    {
        $meta = Str::isUuid($uploadId) ? Cache::get($this->key($uploadId)) : null;

        if (! is_array($meta) || ($meta['user_id'] ?? null) !== $user->id) {
            throw ValidationException::withMessages(['upload' => __('challenges.invalid_upload')]);
        }

        /** @var array{user_id: int, size: int, next_chunk: int, completed: bool} $meta */
        return $meta;
    }
}

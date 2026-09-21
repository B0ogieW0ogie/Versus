<?php

namespace App\Jobs;

use App\Actions\Challenges\MarkEntryFailedAction;
use App\Actions\Challenges\MarkEntryReadyAction;
use App\Models\ChallengeEntry;
use App\Services\Video\VideoProcessingException;
use App\Services\Video\VideoTranscoder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessEntryVideo implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public readonly int $entryId)
    {
        // Transcodes are slow; keep them off the default queue so verification/reset mail is not delayed.
        $this->onQueue('videos');
    }

    public function handle(VideoTranscoder $transcoder, MarkEntryReadyAction $markReady, MarkEntryFailedAction $markFailed): void
    {
        $entry = ChallengeEntry::find($this->entryId);
        if ($entry === null || $entry->status !== ChallengeEntry::STATUS_PROCESSING || $entry->source_path === null) {
            return;
        }

        $local = Storage::disk('local');
        $public = Storage::disk('public');
        $source = $local->path($entry->source_path);
        $maxSeconds = (int) config('versus.challenges.max_video_seconds');
        $maxBytes = (int) config('versus.challenges.max_upload_mb') * 1024 * 1024;

        try {
            $probe = $transcoder->probe($source);

            if (! $probe->hasVideo) {
                throw VideoProcessingException::because('reason_no_video');
            }
            // With a trim only the kept part counts; the cut can't run past the real end of the video.
            $start = $entry->trim_start_ms;
            $end = $entry->trim_end_ms !== null && $probe->durationMs > 0 ? min($entry->trim_end_ms, $probe->durationMs) : $entry->trim_end_ms;
            $durationMs = $end !== null ? $end - (int) $start : $probe->durationMs;
            if ($durationMs > $maxSeconds * 1000 + 500) {
                throw VideoProcessingException::because('reason_too_long');
            }
            if ($end !== null && $durationMs <= 0) {
                throw VideoProcessingException::because('reason_generic');
            }
            if ((int) filesize($source) > $maxBytes) {
                throw VideoProcessingException::because('reason_too_big');
            }

            $uuid = (string) Str::uuid();
            $videoPath = "videos/{$uuid}.mp4";
            $posterPath = "posters/{$uuid}.jpg";
            $public->makeDirectory('videos');
            $public->makeDirectory('posters');

            $transcoder->transcode($source, $public->path($videoPath), $start, $end);
            $transcoder->poster($public->path($videoPath), $public->path($posterPath));
        } catch (VideoProcessingException $e) {
            $markFailed($entry, $e->reasonKey);

            return;
        }

        $markReady($entry, $videoPath, $posterPath, $durationMs);
    }

    public function failed(?Throwable $exception): void
    {
        $entry = ChallengeEntry::find($this->entryId);

        if ($entry !== null) {
            app(MarkEntryFailedAction::class)($entry, 'reason_generic');
        }
    }
}

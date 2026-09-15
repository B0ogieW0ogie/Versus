<?php

namespace App\Console\Commands;

use App\Actions\Challenges\MarkEntryFailedAction;
use App\Models\ChallengeEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupChallengeUploadsCommand extends Command
{
    protected $signature = 'challenges:cleanup-uploads';

    protected $description = 'Delete abandoned chunked uploads and fail entries stuck in processing.';

    public function handle(MarkEntryFailedAction $markFailed): int
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subDay()->getTimestamp();

        foreach ($disk->directories('uploads') as $dir) {
            $mtime = @filemtime($disk->path($dir));
            if ($mtime !== false && $mtime < $cutoff) {
                $disk->deleteDirectory($dir);
            }
        }

        ChallengeEntry::query()
            ->where('status', ChallengeEntry::STATUS_PROCESSING)
            ->where('created_at', '<', now()->subDay())
            ->eachById(fn (ChallengeEntry $entry) => $markFailed($entry, 'reason_generic'));

        return self::SUCCESS;
    }
}

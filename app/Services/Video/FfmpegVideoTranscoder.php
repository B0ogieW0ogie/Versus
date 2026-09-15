<?php

namespace App\Services\Video;

use Illuminate\Support\Facades\Process;

class FfmpegVideoTranscoder implements VideoTranscoder
{
    private const FIT_720P = 'scale=720:1280:force_original_aspect_ratio=decrease,pad=720:1280:(ow-iw)/2:(oh-ih)/2:color=black,setsar=1';

    public function probe(string $path): VideoProbe
    {
        $result = Process::timeout(60)->run(implode(' ', [
            'ffprobe', '-v', 'error',
            '-show_entries', 'stream=codec_type:format=duration',
            '-of', 'json', escapeshellarg($path),
        ]));

        if ($result->failed()) {
            throw VideoProcessingException::because('reason_generic');
        }

        /** @var array{streams?: mixed, format?: mixed}|null $json */
        $json = json_decode($result->output(), true);
        if (! is_array($json)) {
            throw VideoProcessingException::because('reason_generic');
        }

        $streams = is_array($json['streams'] ?? null) ? $json['streams'] : [];
        $hasVideo = collect($streams)->contains(fn ($s): bool => is_array($s) && ($s['codec_type'] ?? null) === 'video');

        $format = is_array($json['format'] ?? null) ? $json['format'] : [];
        $durationMs = (int) round(((float) ($format['duration'] ?? 0)) * 1000);

        return new VideoProbe($hasVideo, $durationMs);
    }

    public function transcode(string $input, string $output): void
    {
        $this->run([
            'ffmpeg', '-y', '-i', escapeshellarg($input),
            '-vf', escapeshellarg(self::FIT_720P),
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', '-pix_fmt', 'yuv420p',
            '-c:a', 'aac', '-b:a', '128k',
            '-movflags', '+faststart',
            // Cap the output length: the container header duration checked by probe() can lie.
            '-t', (string) ((int) config('versus.challenges.max_video_seconds') + 0.5),
            escapeshellarg($output),
        ]);
    }

    public function poster(string $input, string $output): void
    {
        $this->run([
            'ffmpeg', '-y', '-ss', '0.5', '-i', escapeshellarg($input),
            '-frames:v', '1', '-vf', escapeshellarg(self::FIT_720P), '-q:v', '3',
            escapeshellarg($output),
        ]);
    }

    /**
     * Runs a command built from literal ffmpeg tokens plus already-escaped path
     * arguments, joined into a shell command line (rather than passed as an
     * argv array) so that Process::fake patterns like 'ffmpeg*' match the
     * resulting command string.
     *
     * @param  list<string>  $command
     */
    private function run(array $command): void
    {
        $result = Process::timeout(280)->run(implode(' ', $command));

        if ($result->failed()) {
            throw VideoProcessingException::because('reason_generic');
        }
    }
}

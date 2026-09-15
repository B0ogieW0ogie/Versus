<?php

namespace Tests\Feature\Challenges;

use App\Services\Video\FfmpegVideoTranscoder;
use App\Services\Video\VideoProcessingException;
use App\Services\Video\VideoTranscoder;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class FfmpegVideoTranscoderTest extends TestCase
{
    public function test_container_resolves_ffmpeg_transcoder(): void
    {
        $this->assertInstanceOf(FfmpegVideoTranscoder::class, app(VideoTranscoder::class));
    }

    public function test_probe_parses_ffprobe_json(): void
    {
        Process::fake([
            'ffprobe*' => Process::result(json_encode([
                'streams' => [['codec_type' => 'audio'], ['codec_type' => 'video']],
                'format' => ['duration' => '12.345'],
            ])),
        ]);

        $probe = app(FfmpegVideoTranscoder::class)->probe('/tmp/in');

        $this->assertTrue($probe->hasVideo);
        $this->assertSame(12345, $probe->durationMs);
    }

    public function test_probe_failure_is_a_generic_processing_error(): void
    {
        Process::fake(['ffprobe*' => Process::result('', 'broken', 1)]);

        try {
            app(FfmpegVideoTranscoder::class)->probe('/tmp/in');
            $this->fail('Expected exception');
        } catch (VideoProcessingException $e) {
            $this->assertSame('reason_generic', $e->reasonKey);
        }
    }

    public function test_transcode_runs_ffmpeg_with_faststart_720p(): void
    {
        Process::fake(['ffmpeg*' => Process::result()]);

        app(FfmpegVideoTranscoder::class)->transcode('/tmp/in', '/tmp/out.mp4');

        Process::assertRan(fn ($process) => str_contains(implode(' ', (array) $process->command), '+faststart')
            && str_contains(implode(' ', (array) $process->command), 'scale=720:1280'));
    }
}

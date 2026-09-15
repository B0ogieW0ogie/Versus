<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Video\UploadStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ChallengeUploadController extends Controller
{
    public function __construct(private readonly UploadStore $uploads) {}

    public function start(Request $request): JsonResponse
    {
        return response()->json([
            'upload_id' => $this->uploads->start($this->user($request)),
            'chunk_bytes' => $this->uploads->chunkBytes(),
        ]);
    }

    public function chunk(Request $request, string $upload): JsonResponse
    {
        $data = $request->validate([
            'index' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('chunk');
        $this->uploads->appendChunk($this->user($request), $upload, (int) $data['index'], $file);

        return response()->json($this->uploads->status($this->user($request), $upload));
    }

    public function status(Request $request, string $upload): JsonResponse
    {
        return response()->json($this->uploads->status($this->user($request), $upload));
    }

    public function complete(Request $request, string $upload): JsonResponse
    {
        $this->uploads->complete($this->user($request), $upload);

        return response()->json($this->uploads->status($this->user($request), $upload));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminMediaUploadRequest;
use App\Services\ContentMediaService;
use Illuminate\Http\JsonResponse;

/** Return generated asset references for separately authorized content edits. */
class AdminMediaController extends Controller
{
    /** Inject public asset storage operations. */
    public function __construct(private ContentMediaService $media) {}

    /** Store a validated icon and return its public reference with HTTP 201. */
    public function icon(AdminMediaUploadRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->media->icon($request->file('file'))], 201)->header('Cache-Control', 'no-store');
    }

    /** Store validated audio and return its public reference with HTTP 201. */
    public function audio(AdminMediaUploadRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->media->audio($request->file('file'))], 201)->header('Cache-Control', 'no-store');
    }
}

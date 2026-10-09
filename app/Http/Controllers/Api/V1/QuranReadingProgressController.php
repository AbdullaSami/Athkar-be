<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuranTrackingRequest;
use App\Http\Resources\QuranTrackingResource;
use App\Services\QuranTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/** Coordinate the caller's singleton Quran reading position. */
class QuranReadingProgressController extends Controller
{
    /** Inject account-scoped tracking operations. */
    public function __construct(private QuranTrackingService $tracking) {}

    /** Return the saved position or explicit null without creating a record. */
    public function show(QuranTrackingRequest $request): JsonResponse
    {
        $progress = $this->tracking->progress($request->user());

        return ($progress ? (new QuranTrackingResource($progress))->response() : response()->json(['data' => null]))
            ->header('Cache-Control', 'no-store');
    }

    /** Save the latest valid reading position and assign a server timestamp. */
    public function update(QuranTrackingRequest $request): JsonResponse
    {
        return (new QuranTrackingResource($this->tracking->saveProgress($request->user(), $request->validated())))
            ->response()->setStatusCode(200)->header('Cache-Control', 'no-store');
    }

    /** Clear only the caller's saved position. */
    public function destroy(QuranTrackingRequest $request): Response
    {
        $this->tracking->deleteProgress($request->user());

        return response()->noContent()->header('Cache-Control', 'no-store');
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuranPreferenceRequest;
use App\Http\Resources\QuranPreferenceResource;
use App\Services\QuranTrackingService;
use Illuminate\Http\JsonResponse;

/** Coordinate authenticated Quran preference reads and partial updates. */
class QuranPreferenceController extends Controller
{
    /** Reuse account-scoped Quran persistence operations. */
    public function __construct(private QuranTrackingService $tracking) {}

    /** Return saved selections or defaults without writing. */
    public function show(QuranPreferenceRequest $request): JsonResponse
    {
        return (new QuranPreferenceResource($this->tracking->preferences($request->user())))->response()
            ->header('Cache-Control', 'no-store');
    }

    /** Save validated selections and return HTTP 200 for the singleton resource. */
    public function update(QuranPreferenceRequest $request): JsonResponse
    {
        return (new QuranPreferenceResource($this->tracking->savePreferences($request->user(), $request->validated())))
            ->response()->setStatusCode(200)->header('Cache-Control', 'no-store');
    }
}

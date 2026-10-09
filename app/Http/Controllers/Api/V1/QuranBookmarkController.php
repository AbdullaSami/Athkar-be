<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuranTrackingRequest;
use App\Http\Resources\QuranTrackingResource;
use App\Services\QuranTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/** Coordinate owned Quran Bookmark records using validated requests and resources. */
class QuranBookmarkController extends Controller
{
    /** Inject the account-scoped tracking service. */
    public function __construct(private QuranTrackingService $tracking) {}

    /** Return a bounded page of this account's records. */
    public function index(QuranTrackingRequest $request): JsonResponse
    {
        return QuranTrackingResource::collection($this->tracking->list($request->user(), false, $request->integer('per_page', 20)))
            ->response()->header('Cache-Control', 'no-store');
    }

    /** Save a valid reference, returning 201 for creation or 200 for an existing record. */
    public function store(QuranTrackingRequest $request): JsonResponse
    {
        $record = $this->tracking->save($request->user(), $request->validated(), false);

        return (new QuranTrackingResource($record))->response()->setStatusCode($record->wasRecentlyCreated ? 201 : 200)
            ->header('Cache-Control', 'no-store');
    }

    /** Return an owned record; another account's ID returns 404. */
    public function show(QuranTrackingRequest $request, string $bookmark): JsonResponse
    {
        return (new QuranTrackingResource($this->tracking->find($request->user(), $bookmark, false)))
            ->response()->header('Cache-Control', 'no-store');
    }

    /** Update only the label of an owned bookmark. */
    public function update(QuranTrackingRequest $request, string $bookmark): JsonResponse
    {
        return (new QuranTrackingResource($this->tracking->updateBookmark($request->user(), $bookmark, $request->validated())))
            ->response()->header('Cache-Control', 'no-store');
    }

    /** Delete an owned record and return 204. */
    public function destroy(QuranTrackingRequest $request, string $bookmark): Response
    {
        $this->tracking->delete($request->user(), $bookmark, false);

        return response()->noContent()->header('Cache-Control', 'no-store');
    }
}

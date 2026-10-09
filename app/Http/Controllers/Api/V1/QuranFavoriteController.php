<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuranTrackingRequest;
use App\Http\Resources\QuranTrackingResource;
use App\Services\QuranTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/** Coordinate owned Quran Favorite records using validated requests and resources. */
class QuranFavoriteController extends Controller
{
    /** Inject the account-scoped tracking service. */
    public function __construct(private QuranTrackingService $tracking) {}

    /** Return a bounded page of this account's records. */
    public function index(QuranTrackingRequest $request): JsonResponse
    {
        return QuranTrackingResource::collection($this->tracking->list($request->user(), true, $request->integer('per_page', 20)))
            ->response()->header('Cache-Control', 'no-store');
    }

    /** Save a valid reference, returning 201 for creation or 200 for an existing record. */
    public function store(QuranTrackingRequest $request): JsonResponse
    {
        $record = $this->tracking->save($request->user(), $request->validated(), true);

        return (new QuranTrackingResource($record))->response()->setStatusCode($record->wasRecentlyCreated ? 201 : 200)
            ->header('Cache-Control', 'no-store');
    }

    /** Return an owned record; another account's ID returns 404. */
    public function show(QuranTrackingRequest $request, string $favorite): JsonResponse
    {
        return (new QuranTrackingResource($this->tracking->find($request->user(), $favorite, true)))
            ->response()->header('Cache-Control', 'no-store');
    }

    /** Delete an owned record and return 204. */
    public function destroy(QuranTrackingRequest $request, string $favorite): Response
    {
        $this->tracking->delete($request->user(), $favorite, true);

        return response()->noContent()->header('Cache-Control', 'no-store');
    }
}

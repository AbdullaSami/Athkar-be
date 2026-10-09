<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wird\PersonalWirdRequest;
use App\Http\Resources\PersonalWirdResource;
use App\Services\PersonalWirdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Coordinate the same owned Wird operations for guests and registered accounts. */
class PersonalWirdController extends Controller
{
    /** Inject the owned operation service. */
    public function __construct(private PersonalWirdService $wirds) {}

    /** List this owner's items with bounded pagination. */
    public function index(PersonalWirdRequest $request): AnonymousResourceCollection
    {
        return PersonalWirdResource::collection($this->wirds->list($request->owner(), $request->integer('per_page', 20)));
    }

    /** Create validated content with a zero counter and return HTTP 201. */
    public function store(PersonalWirdRequest $request): JsonResponse
    {
        $wird = $this->wirds->create($request->owner(), $request->validated());

        return (new PersonalWirdResource($wird))->response()->setStatusCode(201);
    }

    /** Resolve the ID within the caller's ownership scope. */
    public function show(PersonalWirdRequest $request, string $wird): PersonalWirdResource
    {
        return new PersonalWirdResource($this->wirds->find($request->owner(), $wird));
    }

    /** Update validated text or target without changing ownership or count. */
    public function update(PersonalWirdRequest $request, string $wird): PersonalWirdResource
    {
        return new PersonalWirdResource($this->wirds->update($request->owner(), $wird, $request->validated()));
    }

    /** Delete an owned item and return HTTP 204. */
    public function destroy(PersonalWirdRequest $request, string $wird): Response
    {
        $this->wirds->delete($request->owner(), $wird);

        return response()->noContent();
    }

    /** Add one to the owned counter, including above its target. */
    public function increment(PersonalWirdRequest $request, string $wird): PersonalWirdResource
    {
        return new PersonalWirdResource($this->wirds->count($request->owner(), $wird));
    }

    /** Reset the owned counter to zero. */
    public function reset(PersonalWirdRequest $request, string $wird): PersonalWirdResource
    {
        return new PersonalWirdResource($this->wirds->count($request->owner(), $wird, reset: true));
    }
}

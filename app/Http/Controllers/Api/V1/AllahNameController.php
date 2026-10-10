<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AllahNameRequest;
use App\Http\Resources\AllahNameResource;
use App\Services\AllahNameService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Coordinate validated public reads from the local Asma reference. */
class AllahNameController extends Controller
{
    /** Inject the local reference service. */
    public function __construct(private AllahNameService $names) {}

    /** Return a paginated list with a default of twenty records. */
    public function index(AllahNameRequest $request): AnonymousResourceCollection
    {
        return AllahNameResource::collection($this->names->list($request->validated('language', 'en'), $request->integer('per_page', 20)));
    }

    /** Return the requested name number and meaning or HTTP 404. */
    public function show(AllahNameRequest $request, string $number): AllahNameResource
    {
        return new AllahNameResource($this->names->find((int) $number, $request->validated('language', 'en')));
    }
}

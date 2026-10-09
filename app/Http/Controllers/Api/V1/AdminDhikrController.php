<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDhikrRequest;
use App\Http\Resources\AdminContentResource;
use App\Models\Dhikr;
use App\Services\AdminContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Coordinate authorized Dhikr editing through validated requests and the content service. */
class AdminDhikrController extends Controller
{
    /** Inject transactional content operations. */
    public function __construct(private AdminContentService $content) {}

    /** Return a bounded editor page including draft metadata. */
    public function index(AdminDhikrRequest $request): AnonymousResourceCollection
    {
        return AdminContentResource::collection($this->content->list(Dhikr::class, $request->validated()));
    }

    /** Create validated content and return its representation with HTTP 201. */
    public function store(AdminDhikrRequest $request): JsonResponse
    {
        return (new AdminContentResource($this->content->create(Dhikr::class, $request->validated())))
            ->response()->setStatusCode(201);
    }

    /** Return the policy-authorized bound record. */
    public function show(AdminDhikrRequest $request, Dhikr $dhikr): AdminContentResource
    {
        return new AdminContentResource($dhikr);
    }

    /** Update only validated fields after checking the latest publication state. */
    public function update(AdminDhikrRequest $request, Dhikr $dhikr): AdminContentResource
    {
        return new AdminContentResource($this->content->update($dhikr, $request->validated()));
    }

    /** Delete an authorized record when no dependent records exist. */
    public function destroy(AdminDhikrRequest $request, Dhikr $dhikr): Response
    {
        $this->content->delete($dhikr);

        return response()->noContent();
    }
}

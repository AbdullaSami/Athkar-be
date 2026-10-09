<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminSubcategoryRequest;
use App\Http\Resources\AdminContentResource;
use App\Models\Subcategory;
use App\Services\AdminContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Coordinate authorized Subcategory editing through validated requests and the content service. */
class AdminSubcategoryController extends Controller
{
    /** Inject transactional content operations. */
    public function __construct(private AdminContentService $content) {}

    /** Return a bounded editor page including draft metadata. */
    public function index(AdminSubcategoryRequest $request): AnonymousResourceCollection
    {
        return AdminContentResource::collection($this->content->list(Subcategory::class, $request->validated()));
    }

    /** Create validated content and return its representation with HTTP 201. */
    public function store(AdminSubcategoryRequest $request): JsonResponse
    {
        return (new AdminContentResource($this->content->create(Subcategory::class, $request->validated())))
            ->response()->setStatusCode(201);
    }

    /** Return the policy-authorized bound record. */
    public function show(AdminSubcategoryRequest $request, Subcategory $subcategory): AdminContentResource
    {
        return new AdminContentResource($subcategory);
    }

    /** Update only validated fields after checking the latest publication state. */
    public function update(AdminSubcategoryRequest $request, Subcategory $subcategory): AdminContentResource
    {
        return new AdminContentResource($this->content->update($subcategory, $request->validated()));
    }

    /** Delete an authorized record when no dependent records exist. */
    public function destroy(AdminSubcategoryRequest $request, Subcategory $subcategory): Response
    {
        $this->content->delete($subcategory);

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminCategoryRequest;
use App\Http\Resources\AdminContentResource;
use App\Models\Category;
use App\Services\AdminContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Coordinate authorized Category editing through validated requests and the content service. */
class AdminCategoryController extends Controller
{
    /** Inject transactional content operations. */
    public function __construct(private AdminContentService $content) {}

    /** Return a bounded editor page including draft metadata. */
    public function index(AdminCategoryRequest $request): AnonymousResourceCollection
    {
        return AdminContentResource::collection($this->content->list(Category::class, $request->validated()));
    }

    /** Create validated content and return its representation with HTTP 201. */
    public function store(AdminCategoryRequest $request): JsonResponse
    {
        return (new AdminContentResource($this->content->create(Category::class, $request->validated())))
            ->response()->setStatusCode(201);
    }

    /** Return the policy-authorized bound record. */
    public function show(AdminCategoryRequest $request, Category $category): AdminContentResource
    {
        return new AdminContentResource($category);
    }

    /** Update only validated fields after checking the latest publication state. */
    public function update(AdminCategoryRequest $request, Category $category): AdminContentResource
    {
        return new AdminContentResource($this->content->update($category, $request->validated()));
    }

    /** Delete an authorized record when no dependent records exist. */
    public function destroy(AdminCategoryRequest $request, Category $category): Response
    {
        $this->content->delete($category);

        return response()->noContent();
    }
}

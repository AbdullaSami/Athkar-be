<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContentReadRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\PublicContentService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Public category browsing with validated pagination. */
class CategoryController extends Controller
{
    /** Inject published-content read operations. */
    public function __construct(private PublicContentService $content) {}

    /** Return a bounded page of category metadata. */
    public function index(ContentReadRequest $request): AnonymousResourceCollection
    {
        return CategoryResource::collection($this->content->categories((int) $request->validated('per_page', 20)));
    }

    /** Return one category resolved by constrained route binding. */
    public function show(ContentReadRequest $request, Category $category): CategoryResource
    {
        return new CategoryResource($category);
    }
}

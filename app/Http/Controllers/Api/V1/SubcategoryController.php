<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContentReadRequest;
use App\Http\Resources\SubcategoryResource;
use App\Models\Category;
use App\Models\Subcategory;
use App\Services\PublicContentService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Public subcategory browsing with published-only counts. */
class SubcategoryController extends Controller
{
    /** Inject published-content read operations. */
    public function __construct(private PublicContentService $content) {}

    /** Return only children of the route's category. */
    public function index(ContentReadRequest $request, Category $category): AnonymousResourceCollection
    {
        return SubcategoryResource::collection($this->content->subcategories($category, (int) $request->validated('per_page', 20)));
    }

    /** Return one subcategory and its published-content count. */
    public function show(ContentReadRequest $request, Subcategory $subcategory): SubcategoryResource
    {
        return new SubcategoryResource($this->content->subcategory($subcategory));
    }
}

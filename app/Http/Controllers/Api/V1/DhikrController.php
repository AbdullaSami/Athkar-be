<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContentReadRequest;
use App\Http\Resources\DhikrResource;
use App\Models\Subcategory;
use App\Services\PublicContentService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Deliver published Dhikr only; counters and progress are separate concerns. */
class DhikrController extends Controller
{
    /** Inject publication-aware content operations. */
    public function __construct(private PublicContentService $content) {}

    /** Return a bounded page belonging to the route's subcategory. */
    public function index(ContentReadRequest $request, Subcategory $subcategory): AnonymousResourceCollection
    {
        return DhikrResource::collection($this->content->dhikrs($subcategory, (int) $request->validated('per_page', 20)));
    }

    /** Return one published item with neighbors confined to its own subcategory. */
    public function show(ContentReadRequest $request, string $dhikr): DhikrResource
    {
        $result = $this->content->dhikr($dhikr);

        return (new DhikrResource($result['dhikr']))->additional(['meta' => $result['navigation']]);
    }
}

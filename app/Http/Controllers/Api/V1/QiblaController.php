<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\QiblaRequest;
use App\Services\AlAdhanService;
use Illuminate\Http\JsonResponse;

/** Validate Qibla input and return mapped provider data only. */
class QiblaController extends Controller
{
    /** Inject the shared AlAdhan client. */
    public function __construct(private AlAdhanService $aladhan) {}

    /** Input: validated feature parameters. Output: normalized JSON with no browser location cache. */
    public function __invoke(QiblaRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->aladhan->qibla($request->validated())])->header('Cache-Control', 'no-store');
    }
}

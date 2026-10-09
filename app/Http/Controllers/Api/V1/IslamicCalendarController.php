<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IslamicCalendarRequest;
use App\Services\AlAdhanService;
use Illuminate\Http\JsonResponse;

/** Validate IslamicCalendar input and return mapped provider data only. */
class IslamicCalendarController extends Controller
{
    /** Inject the shared AlAdhan client. */
    public function __construct(private AlAdhanService $aladhan) {}

    /** Input: validated feature parameters. Output: normalized JSON with no browser location cache. */
    public function __invoke(IslamicCalendarRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->aladhan->calendar($request->validated())])->header('Cache-Control', 'no-store');
    }
}

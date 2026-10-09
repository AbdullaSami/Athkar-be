<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuranReadRequest;
use App\Services\QuranService;
use Illuminate\Http\JsonResponse;

/** Return validated provider metadata through the Quran service. */
class QuranSurahController extends Controller
{
    /** Inject the normalized Quran provider service. */
    public function __construct(private QuranService $quran) {}

    /** Return a bounded page of surah metadata. */
    public function index(QuranReadRequest $request): JsonResponse
    {
        return response()->json($this->quran->surahs($request->integer('page', 1), $request->integer('per_page', 20)));
    }

    /** Return metadata for a validated surah number. */
    public function show(QuranReadRequest $request, string $surah): JsonResponse
    {
        return response()->json(['data' => $this->quran->surah((int) $surah)]);
    }
}

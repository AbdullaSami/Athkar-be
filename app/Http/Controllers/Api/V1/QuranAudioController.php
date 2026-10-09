<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuranReadRequest;
use App\Services\QuranService;
use Illuminate\Http\JsonResponse;

/** Return validated CDN playback URLs without proxying or storing audio files. */
class QuranAudioController extends Controller
{
    /** Reuse the dedicated Quran provider service. */
    public function __construct(private QuranService $quran) {}

    /** Return one bounded page of a supported reciter's audio URLs. */
    public function index(QuranReadRequest $request, string $surah): JsonResponse
    {
        return response()->json($this->quran->audio((int) $surah, $request->validated('reciter', 'ar.alafasy'),
            $request->integer('page', 1), $request->integer('per_page', 20)));
    }
}

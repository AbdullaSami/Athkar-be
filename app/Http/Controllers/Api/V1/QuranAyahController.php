<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuranReadRequest;
use App\Services\QuranService;
use Illuminate\Http\JsonResponse;

/** Return paginated Quran text without coupling provider payloads to Eloquent models. */
class QuranAyahController extends Controller
{
    /** Inject the normalized Quran provider service. */
    public function __construct(private QuranService $quran) {}

    /** Return one supported edition's ayahs for a validated surah. */
    public function index(QuranReadRequest $request, string $surah): JsonResponse
    {
        return response()->json($this->quran->ayahs((int) $surah, $request->validated('edition', 'quran-uthmani'),
            $request->integer('page', 1), $request->integer('per_page', 20)));
    }
}

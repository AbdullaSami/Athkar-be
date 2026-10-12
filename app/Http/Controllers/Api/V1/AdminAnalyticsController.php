<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EmptyAuthRequest;
use App\Services\AdminAnalyticsService;
use Illuminate\Http\JsonResponse;

/** Return aggregate-only dashboard analytics behind session and permission middleware. */
class AdminAnalyticsController extends Controller
{
    /** Input: no query or body fields. Output: current counts without personal records. */
    public function __invoke(EmptyAuthRequest $request, AdminAnalyticsService $analytics): JsonResponse
    {
        return response()->json(['data' => $analytics->snapshot()]);
    }
}

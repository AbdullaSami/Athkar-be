<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrayerSettingRequest;
use App\Http\Resources\PrayerSettingResource;
use App\Services\PrayerSettingService;
use Illuminate\Http\JsonResponse;

/** Coordinate authenticated reads and partial updates of prayer preferences. */
class PrayerSettingController extends Controller
{
    /** Inject the account-scoped persistence service. */
    public function __construct(private PrayerSettingService $settings) {}

    /** Return the caller's preferences or defaults without writing. */
    public function show(PrayerSettingRequest $request): JsonResponse
    {
        return (new PrayerSettingResource($this->settings->show($request->user())))
            ->response()->header('Cache-Control', 'no-store');
    }

    /** Save validated partial preferences and return the full current settings. */
    public function update(PrayerSettingRequest $request): JsonResponse
    {
        return (new PrayerSettingResource($this->settings->update($request->user(), $request->validated())))
            ->response()->setStatusCode(200)->header('Cache-Control', 'no-store');
    }
}

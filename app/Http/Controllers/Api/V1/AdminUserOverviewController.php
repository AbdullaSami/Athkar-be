<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminUserOverviewRequest;
use App\Http\Resources\AdminGuestResource;
use App\Http\Resources\AdminUserResource;
use App\Services\AdminUserOverviewService;
use Illuminate\Http\JsonResponse;

/** Coordinate read-only, permission-protected user overview responses. */
class AdminUserOverviewController extends Controller
{
    /** Inject account and guest queries. */
    public function __construct(private AdminUserOverviewService $overview) {}

    /** Return record totals; these do not measure unique visitors or active users. */
    public function show(AdminUserOverviewRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->overview->totals()])->header('Cache-Control', 'no-store');
    }

    /** Return only validated pagination and minimal account fields. */
    public function users(AdminUserOverviewRequest $request): JsonResponse
    {
        return AdminUserResource::collection($this->overview->users($request->validated()))
            ->response()->header('Cache-Control', 'no-store');
    }

    /** Return a bounded page of unclaimed anonymous identities. */
    public function guests(AdminUserOverviewRequest $request): JsonResponse
    {
        return AdminGuestResource::collection($this->overview->guests($request->validated()))
            ->response()->header('Cache-Control', 'no-store');
    }
}

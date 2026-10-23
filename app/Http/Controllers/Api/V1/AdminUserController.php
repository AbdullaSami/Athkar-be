<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminUserRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use App\Services\AdminUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/** Coordinate validated user management while reusing the existing overview list endpoint. */
class AdminUserController extends Controller
{
    /** Inject transactional user operations. */
    public function __construct(private AdminUserService $users) {}

    /** Create an ordinary account; privileges are assigned through the explicit roles endpoint. */
    public function store(AdminUserRequest $request): JsonResponse
    {
        return (new AdminUserResource($this->users->save($request->user(), $request->validated())))->response()->setStatusCode(201);
    }

    /** Return one account and its current web roles. */
    public function show(AdminUserRequest $request, User $user): AdminUserResource
    {
        return new AdminUserResource($this->users->show($user));
    }

    /** Update explicit profile fields or status, revoking credentials where required. */
    public function update(AdminUserRequest $request, User $user): AdminUserResource
    {
        return new AdminUserResource($this->users->save($request->user(), $request->validated(), $user));
    }

    /** Replace role membership with validated web-role IDs. */
    public function roles(AdminUserRequest $request, User $user): AdminUserResource
    {
        return new AdminUserResource($this->users->roles($request->user(), $user, $request->validated('role_ids')));
    }

    /** Permanently remove an authorized account and its owned data. */
    public function destroy(AdminUserRequest $request, User $user): Response
    {
        $this->users->delete($request->user(), $user);

        return response()->noContent();
    }
}

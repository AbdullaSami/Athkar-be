<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminRoleRequest;
use App\Http\Resources\AdminRoleResource;
use App\Services\AdminRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Spatie\Permission\Models\Role;

/** Coordinate validated role management behind dashboard session and explicit authorization. */
class AdminRoleController extends Controller
{
    /** Inject transactional role operations. */
    public function __construct(private AdminRoleService $roles) {}

    /** Return paginated web roles and their permissions. */
    public function index(AdminRoleRequest $request): JsonResponse
    {
        return AdminRoleResource::collection($this->roles->roles($request->validated()))->response();
    }

    /** Return a bounded catalog of code-defined web permissions. */
    public function permissions(AdminRoleRequest $request): JsonResponse
    {
        return response()->json($this->roles->permissions($request->validated()));
    }

    /** Create a role using explicit fields and caller-held permissions only. */
    public function store(AdminRoleRequest $request): JsonResponse
    {
        return (new AdminRoleResource($this->roles->save($request->user(), $request->validated())))->response()->setStatusCode(201);
    }

    /** Return one authorized web role. */
    public function show(AdminRoleRequest $request, Role $role): AdminRoleResource
    {
        return new AdminRoleResource($this->roles->show($role));
    }

    /** Update a role after transactional escalation and self-lockout checks. */
    public function update(AdminRoleRequest $request, Role $role): AdminRoleResource
    {
        return new AdminRoleResource($this->roles->save($request->user(), $request->validated(), $role));
    }

    /** Delete an unassigned role or return a controlled conflict. */
    public function destroy(AdminRoleRequest $request, Role $role): Response
    {
        $this->roles->delete($request->user(), $role);

        return response()->noContent();
    }
}

<?php

namespace App\Services;

use App\AccountStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** Manage existing web roles transactionally without granting privileges beyond the caller's own. */
class AdminRoleService
{
    /** @param array{page?: int, per_page?: int} $input Return a bounded web-role page with eager permissions. */
    public function roles(array $input): LengthAwarePaginator
    {
        return Role::query()->where('guard_name', 'web')->with('permissions')->orderBy('id')
            ->paginate($input['per_page'] ?? 20, ['*'], 'page', $input['page'] ?? 1)->appends($input);
    }

    /** @param array{page?: int, per_page?: int} $input Return existing permission definitions; creation is code-owned. */
    public function permissions(array $input): LengthAwarePaginator
    {
        return Permission::query()->where('guard_name', 'web')->orderBy('id')
            ->paginate($input['per_page'] ?? 20, ['id', 'name'], 'page', $input['page'] ?? 1)->appends($input);
    }

    /** Return an existing web role only, without leaking other authentication guards. */
    public function show(Role $role): Role
    {
        abort_unless($role->guard_name === 'web', 404);

        return $role->load('permissions');
    }

    /** @param array{name?: string, permissions?: list<string>} $input Validated fields; null role creates a new role. */
    public function save(User $actor, array $input, ?Role $role = null): Role
    {
        try {
            return DB::transaction(function () use ($actor, $input, $role): Role {
                $role = $role ? Role::query()->where('guard_name', 'web')->lockForUpdate()->findOrFail($role->id) : new Role(['guard_name' => 'web']);
                $this->authorizeWrite($actor, $role, $input['permissions'] ?? []);
                if (array_key_exists('name', $input)) {
                    $role->name = $input['name'];
                }
                $role->save();
                if (array_key_exists('permissions', $input)) {
                    $role->syncPermissions($input['permissions']);
                }

                return $role->load('permissions');
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'A role with this name already exists.']);
        }
    }

    /** Delete only unassigned web roles after rechecking current permissions under the role lock. */
    public function delete(User $actor, Role $role): void
    {
        DB::transaction(function () use ($actor, $role): void {
            $role = Role::query()->where('guard_name', 'web')->lockForUpdate()->findOrFail($role->id);
            $this->authorizeWrite($actor, $role, []);
            abort_if($role->users()->exists(), 409, 'Assigned roles cannot be deleted.');
            $role->delete();
        });
    }

    /** @param list<string> $requested Prevent self-lockout and changes to permissions the caller does not hold. */
    private function authorizeWrite(User $actor, Role $role, array $requested): void
    {
        $actor = $actor->fresh();
        abort_unless($actor->account_status === AccountStatus::Active && $actor->can('access-dashboard') && $actor->can('manage-roles'), 403);
        abort_if($role->exists && $actor->roles()->whereKey($role->id)->exists(), 403, 'Your assigned roles cannot be changed here.');
        $held = $actor->getAllPermissions()->where('guard_name', 'web')->pluck('name')->all();
        $existing = $role->exists ? $role->permissions()->pluck('name')->all() : [];
        abort_if(array_diff([...$existing, ...$requested], $held) !== [], 403);
    }
}

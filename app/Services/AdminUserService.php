<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/** Manage accounts and role membership with transactional authorization and credential revocation. */
class AdminUserService
{
    /** Share the same privilege-write lock with role management. */
    public function __construct(private AdminAccessService $access) {}

    /** Return an account with explicit web-role metadata, never its credentials or private worship content. */
    public function show(User $user): User
    {
        return $user->load(['roles' => fn ($query) => $query->where('guard_name', 'web')->orderBy('id')]);
    }

    /** @param array<string, mixed> $data Validated profile and optional creation roles; existing account roles use the roles endpoint. */
    public function save(User $actor, array $data, ?User $target = null): User
    {
        try {
            return DB::transaction(function () use ($actor, $data, $target): User {
                $actor = $this->access->lock($actor, 'manage-users');
                $creating = $target === null;
                $target = $target ? User::query()->lockForUpdate()->findOrFail($target->id) : new User;
                if ($target->exists) {
                    $this->access->authorizeTarget($actor, $target);
                }
                $oldEmail = $target->email;
                $target->fill(array_intersect_key($data, array_flip(['name', 'email', 'age', 'phone', 'password'])));
                if (array_key_exists('account_status', $data)) {
                    $target->account_status = $data['account_status'];
                }
                $credentialsChanged = $target->exists && $target->isDirty(['email', 'password', 'account_status']);
                if ($target->isDirty('email')) {
                    $target->email_verified_at = null;
                }
                if ($credentialsChanged) {
                    $target->setRememberToken(Str::random(60));
                }
                $target->save();
                if ($creating && array_key_exists('role_ids', $data)) {
                    $this->assignRoles($actor, $target, $data['role_ids']);
                }
                if ($credentialsChanged) {
                    $this->revoke($target);
                    if ($oldEmail !== $target->email) {
                        $previous = new User(['email' => $oldEmail]);
                        Password::broker()->deleteToken($previous);
                    }
                }

                return $this->show($target->refresh());
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'An account with this email already exists.']);
        }
    }

    /** @param list<int> $roleIds Replace web roles only, with caller-held permissions and no direct-permission edits. */
    public function roles(User $actor, User $target, array $roleIds): User
    {
        return DB::transaction(function () use ($actor, $target, $roleIds): User {
            $actor = $this->access->lock($actor, 'manage-users');
            abort_unless($actor->can('manage-roles'), 403);
            $target = User::query()->lockForUpdate()->findOrFail($target->id);
            $this->access->authorizeTarget($actor, $target);
            $this->assignRoles($actor, $target, $roleIds);
            $target->setRememberToken(Str::random(60));
            $target->save();
            $this->revoke($target);

            return $this->show($target->refresh());
        });
    }

    /** Delete an authorized account, its credentials and ownership records; never allow self-deletion. */
    public function delete(User $actor, User $target): void
    {
        DB::transaction(function () use ($actor, $target): void {
            $actor = $this->access->lock($actor, 'manage-users');
            $target = User::query()->lockForUpdate()->findOrFail($target->id);
            $this->access->authorizeTarget($actor, $target);
            $this->revoke($target);
            $target->syncRoles([]);
            $target->syncPermissions([]);
            $target->delete();
        });
    }

    /** @param list<int> $roleIds Apply only caller-held web-role permissions inside the existing security transaction. */
    private function assignRoles(User $actor, User $target, array $roleIds): void
    {
        abort_unless($actor->can('manage-roles'), 403);
        $roles = Role::query()->where('guard_name', 'web')->whereIn('id', $roleIds)->orderBy('id')->lockForUpdate()->get();
        if ($roles->count() !== count($roleIds)) {
            throw ValidationException::withMessages(['role_ids' => 'One or more roles are unavailable.']);
        }
        $held = $actor->getAllPermissions()->where('guard_name', 'web')->pluck('name')->all();
        foreach ($roles as $role) {
            abort_if($role->permissions()->where('guard_name', '!=', 'web')->exists()
                || array_diff($role->permissions()->pluck('name')->all(), $held) !== [], 403);
        }
        $target->syncRoles($roles);
    }

    /** Revoke mobile tokens, reset proofs and configured database-backed browser sessions. */
    private function revoke(User $target): void
    {
        $target->tokens()->delete();
        Password::broker()->deleteToken($target);
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $target->id)->delete();
        }
    }
}

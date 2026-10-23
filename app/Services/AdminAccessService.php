<?php

namespace App\Services;

use App\AccountStatus;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/** Serialize dashboard privilege writes and recheck permissions from current account records. */
class AdminAccessService
{
    /** Input: transaction-held actor and ability. Output: fresh authorized actor after the shared security lock. */
    public function lock(User $actor, string $ability): User
    {
        abort_unless(Permission::query()->where('name', 'access-dashboard')->where('guard_name', 'web')->lockForUpdate()->first(), 403);
        $actor = $actor->fresh();
        abort_unless($actor && $actor->account_status === AccountStatus::Active && $actor->can('access-dashboard') && $actor->can($ability), 403);

        return $actor;
    }

    /** Prevent self-lockout and mutation of accounts with permissions beyond the caller's own. */
    public function authorizeTarget(User $actor, User $target): void
    {
        abort_if($actor->is($target), 403);
        $held = $actor->getAllPermissions()->where('guard_name', 'web')->pluck('name')->all();
        $targetPermissions = $target->getAllPermissions();
        abort_if($targetPermissions->contains(fn ($permission): bool => $permission->guard_name !== 'web')
            || array_diff($targetPermissions->pluck('name')->all(), $held) !== []
            || $target->roles()->where('guard_name', '!=', 'web')->exists(), 403);
    }
}

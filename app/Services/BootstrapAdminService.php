<?php

namespace App\Services;

use App\AccountStatus;
use App\Models\User;
use Database\Seeders\ContentPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

/** Bootstrap the first administrator only; account creation and credentials remain in existing auth flows. */
class BootstrapAdminService
{
    /** Input: normalized existing email. Output: privileged account; reject any existing dashboard administrator. */
    public function bootstrap(string $email): User
    {
        return DB::transaction(function () use ($email): User {
            (new ContentPermissionSeeder)->run();
            $permission = Permission::query()->where('name', 'access-dashboard')->where('guard_name', 'web')->lockForUpdate()->firstOrFail();
            if (User::query()->permission($permission)->exists()) {
                throw ValidationException::withMessages(['admin' => 'A dashboard administrator already exists. Use the existing administration workflow.']);
            }
            $user = User::query()->where('email', $email)->lockForUpdate()->first();
            if (! $user || $user->account_status !== AccountStatus::Active) {
                throw ValidationException::withMessages(['email' => 'An existing active account is required.']);
            }
            $user->givePermissionTo(ContentPermissionSeeder::PERMISSIONS);

            return $user;
        });
    }
}

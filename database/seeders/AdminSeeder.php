<?php

namespace Database\Seeders;

use App\AccountStatus;
use App\Services\BootstrapAdminService;
use App\Services\SeedAccountService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Seed a local demo administrator through the existing first-administrator bootstrap safeguards. */
class AdminSeeder extends Seeder
{
    /** Create admin@example.test once; never promote a colliding existing regular account or reset passwords. */
    public function run(SeedAccountService $accounts, BootstrapAdminService $bootstrap): void
    {
        DB::transaction(function () use ($accounts, $bootstrap): void {
            $admin = $accounts->account('Demo Administrator', 'admin@example.test');
            if (! $admin->wasRecentlyCreated) {
                if ($admin->account_status !== AccountStatus::Active || collect(ContentPermissionSeeder::PERMISSIONS)->contains(fn (string $permission): bool => ! $admin->can($permission))) {
                    throw new RuntimeException('The demo admin email already belongs to an account without the required active administrator permissions. No changes were made.');
                }

                return;
            }
            $bootstrap->bootstrap($admin->email);
        });
    }
}

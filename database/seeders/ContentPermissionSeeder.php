<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/** Create permission definitions only; never grant privileges to existing accounts. */
class ContentPermissionSeeder extends Seeder
{
    /** Idempotently register the dashboard, user overview, editing and publishing permissions. */
    public function run(): void
    {
        foreach (['access-dashboard', 'view-users', 'manage-content', 'publish-content'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }
}

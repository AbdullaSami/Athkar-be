<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/** Create permission definitions only; never grant privileges to existing accounts. */
class ContentPermissionSeeder extends Seeder
{
    public const PERMISSIONS = ['access-dashboard', 'view-users', 'manage-users', 'view-analytics', 'manage-roles', 'manage-content', 'publish-content'];

    /** Idempotently register the dashboard, analytics, overview and content permissions. */
    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }
}

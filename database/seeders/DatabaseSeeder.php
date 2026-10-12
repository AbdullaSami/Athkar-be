<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Seed production-safe reference data without creating accounts or granting privileges. */
class DatabaseSeeder extends Seeder
{
    /** Insert missing permission definitions and local Names; preserve existing editorial records. */
    public function run(): void
    {
        $this->call([ContentPermissionSeeder::class, AllahNameSeeder::class]);
    }
}

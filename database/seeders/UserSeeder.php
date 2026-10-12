<?php

namespace Database\Seeders;

use App\Services\SeedAccountService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Seed regular demo accounts only when explicitly invoked in local/testing environments. */
class UserSeeder extends Seeder
{
    /** Create user1@example.test through user10@example.test; preserve existing records and grant no permissions. */
    public function run(SeedAccountService $accounts): void
    {
        DB::transaction(function () use ($accounts): void {
            for ($number = 1; $number <= 10; $number++) {
                $accounts->account('Demo User '.$number, 'user'.$number.'@example.test');
            }
        });
    }
}

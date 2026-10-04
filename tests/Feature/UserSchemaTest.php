<?php

use App\AccountStatus;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

test('accounts store age and optional phone with an active default status', function () {
    $user = User::factory()->create(['age' => 24, 'phone' => '+201234567890']);

    $user = $user->fresh();

    expect($user->age)->toBe(24);
    expect($user->phone)->toBe('+201234567890');
    expect($user->account_status)->toBe(AccountStatus::Active);
});

test('Google-only accounts have no password and hide provider identity', function () {
    $user = User::factory()->google()->create();

    $attributes = $user->fresh()->toArray();

    expect($user->password)->toBeNull();
    expect($attributes)->not->toHaveKeys(['password', 'remember_token', 'google_id']);
});

test('one Google identity cannot belong to multiple accounts', function () {
    $user = User::factory()->google()->create();

    expect(fn () => User::factory()->google()->create(['google_id' => $user->google_id]))
        ->toThrow(QueryException::class);

    $this->assertDatabaseCount('users', 1);
});

test('account status and provider identity cannot be changed through mass assignment', function () {
    $user = User::factory()->create()->fresh();

    $user->fill(['account_status' => 'suspended', 'google_id' => 'attacker']);
    $user->save();

    expect($user->fresh()->account_status)->toBe(AccountStatus::Active);
    expect($user->fresh()->google_id)->toBeNull();
});

test('users receive administrative permissions through existing Spatie roles', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $permission = Permission::findOrCreate('publish content', 'web');
    $role = Role::findOrCreate('content reviewer', 'web');
    $role->givePermissionTo($permission);

    $user->assignRole($role);

    expect($user->can('publish content'))->toBeTrue();
    expect($other->can('publish content'))->toBeFalse();
    expect($user->roles->sole()->is($role))->toBeTrue();
});

test('account migration rollback refuses to discard Google-only credentials', function () {
    $user = User::factory()->google()->create();
    $migrationPath = glob(database_path('migrations/*_add_account_fields_to_users_table.php'))[0];
    $migration = require $migrationPath;

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);

    expect($user->fresh()->google_id)->toBe($user->google_id);
    $this->assertModelExists($user);
});

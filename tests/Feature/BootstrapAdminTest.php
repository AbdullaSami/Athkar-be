<?php

use App\AccountStatus;
use App\Models\AllahName;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('production seeding is repeatable and never creates users or grants privileges', function () {
    $user = User::factory()->create();
    $this->seed(DatabaseSeeder::class);
    $name = AllahName::query()->where('number', 1)->where('language', 'en')->firstOrFail();
    $name->meaning = 'Editorial review';
    $name->save();
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('permissions', 6);
    $this->assertDatabaseCount('allah_names', 99);
    $this->assertDatabaseCount('model_has_permissions', 0);
    $this->assertDatabaseCount('model_has_roles', 0);
    expect($name->fresh()->meaning)->toBe('Editorial review');
    expect($user->fresh()->can('access-dashboard'))->toBeFalse();
});

test('explicit bootstrap promotes only the existing target without changing credentials or ownership', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $password = $user->password;
    Permission::findOrCreate('unrelated-private-feature', 'web');

    $this->artisan('admin:bootstrap', ['email' => '  '.strtoupper($user->email).'  ', '--force' => true, '--no-interaction' => true])->assertSuccessful();

    expect($user->fresh()->getAllPermissions()->pluck('name')->sort()->values()->all())->toBe([
        'access-dashboard', 'manage-content', 'manage-roles', 'publish-content', 'view-analytics', 'view-users',
    ]);
    expect($user->fresh()->password)->toBe($password);
    expect($other->fresh()->can('access-dashboard'))->toBeFalse();
    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseCount('personal_access_tokens', 0);
    $this->assertDatabaseCount('roles', 0);
});

test('bootstrap requires operator confirmation and honors cancellation', function () {
    $user = User::factory()->create();
    $this->artisan('admin:bootstrap', ['email' => $user->email])
        ->expectsConfirmation('Grant all dashboard permissions to this existing account?', 'no')->assertFailed();
    $this->assertDatabaseCount('model_has_permissions', 0);
});

test('bootstrap grants permissions after interactive confirmation', function () {
    $user = User::factory()->create();
    $this->artisan('admin:bootstrap', ['email' => $user->email])
        ->expectsConfirmation('Grant all dashboard permissions to this existing account?', 'yes')->assertSuccessful();
    expect($user->fresh()->can('access-dashboard'))->toBeTrue();
});

test('non interactive bootstrap without force grants nothing', function () {
    $user = User::factory()->create();
    $this->artisan('admin:bootstrap', ['email' => $user->email, '--no-interaction' => true])->assertFailed();
    $this->assertDatabaseCount('model_has_permissions', 0);
});

test('bootstrap refuses invalid or missing accounts without granting privileges', function (string $email) {
    $this->artisan('admin:bootstrap', ['email' => $email, '--force' => true])->assertFailed();
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('model_has_permissions', 0);
})->with(['invalid', 'missing@example.com', '']);

test('bootstrap refuses suspended accounts without reactivating them', function () {
    $user = User::factory()->create(['account_status' => AccountStatus::Suspended]);
    $this->artisan('admin:bootstrap', ['email' => $user->email, '--force' => true])->assertFailed();
    expect($user->fresh()->account_status)->toBe(AccountStatus::Suspended);
    $this->assertDatabaseCount('model_has_permissions', 0);
});

test('bootstrap refuses repeat grants even with force and an existing suspended administrator', function (bool $suspended, bool $throughRole) {
    $admin = User::factory()->create(['account_status' => $suspended ? AccountStatus::Suspended : AccountStatus::Active]);
    $target = User::factory()->create();
    $permission = Permission::findOrCreate('access-dashboard', 'web');
    if ($throughRole) {
        $role = Role::findOrCreate('Existing admin', 'web');
        $role->givePermissionTo($permission);
        $admin->assignRole($role);
    } else {
        $admin->givePermissionTo($permission);
    }

    $this->artisan('admin:bootstrap', ['email' => $target->email, '--force' => true])
        ->expectsOutputToContain('A dashboard administrator already exists.')->assertFailed();

    expect($target->fresh()->getAllPermissions())->toHaveCount(0);
    expect($admin->fresh()->getAllPermissions())->toHaveCount(1);
})->with([false, true])->with([false, true]);

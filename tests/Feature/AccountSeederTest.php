<?php

use App\AccountStatus;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config(['auth.seed_account_password' => 'Local-test-password-123!']);
});

test('user seeding creates ten ordinary accounts idempotently with hashed passwords', function () {
    $this->seed(UserSeeder::class);
    $this->seed(UserSeeder::class);

    $this->assertDatabaseCount('users', 10);
    $this->assertDatabaseCount('model_has_permissions', 0);
    $this->assertDatabaseCount('model_has_roles', 0);
    $user = User::query()->where('email', 'user1@example.test')->firstOrFail();
    expect(Hash::check('Local-test-password-123!', $user->password))->toBeTrue();
    expect($user->account_status)->toBe(AccountStatus::Active);
    expect($user->email_verified_at)->toBeNull();
});

test('admin seeding creates an active administrator and preserves credentials on rerun', function () {
    $this->seed(AdminSeeder::class);
    $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();
    $password = $admin->password;
    config(['auth.seed_account_password' => 'Changed-local-password-123!']);
    $this->seed(AdminSeeder::class);

    $this->assertDatabaseCount('users', 1);
    expect($admin->fresh()->getAllPermissions()->pluck('name')->sort()->values()->all())->toBe([
        'access-dashboard', 'manage-content', 'manage-roles', 'publish-content', 'view-analytics', 'view-users',
    ]);
    expect($admin->fresh()->password)->toBe($password);
    expect(Hash::check('Local-test-password-123!', $password))->toBeTrue();
    $this->postJson('/api/v1/admin/auth/login', ['email' => $admin->email, 'password' => 'Local-test-password-123!'])->assertOk();
});

test('demo seeders reject production and staging without creating accounts', function (string $seeder, string $environment) {
    $this->app->instance('env', $environment);
    expect(fn () => $this->app->call([$this->app->make($seeder), 'run']))->toThrow(RuntimeException::class, 'restricted to local and testing');
    $this->assertDatabaseCount('users', 0);
})->with([AdminSeeder::class, UserSeeder::class])->with(['production', 'staging']);

test('demo seeders reject missing or unsupported passwords without creating accounts', function (string $seeder, mixed $password) {
    config(['auth.seed_account_password' => $password]);
    expect(fn () => $this->seed($seeder))->toThrow(RuntimeException::class, 'Set SEED_ACCOUNT_PASSWORD');
    $this->assertDatabaseCount('users', 0);
})->with([AdminSeeder::class, UserSeeder::class])->with([null, '', 'short', str_repeat('a', 73), "long-password\0invalid"]);

test('user seeding preserves existing account credentials and status', function () {
    $user = User::factory()->create(['email' => 'user1@example.test', 'account_status' => AccountStatus::Suspended]);
    $original = $user->fresh()->getAttributes();
    $this->seed(UserSeeder::class);

    expect($user->fresh()->getAttributes())->toBe($original);
    $this->assertDatabaseCount('users', 10);
});

test('admin seeding refuses collisions with an existing regular or suspended account', function (AccountStatus $status) {
    $user = User::factory()->create(['email' => 'admin@example.test', 'account_status' => $status]);
    $original = $user->fresh()->getAttributes();
    expect(fn () => $this->seed(AdminSeeder::class))->toThrow(RuntimeException::class, 'already belongs to an account');

    expect($user->fresh()->getAttributes())->toBe($original);
    $this->assertDatabaseCount('model_has_permissions', 0);
})->with([AccountStatus::Active, AccountStatus::Suspended]);

test('admin seeding respects existing administrator bootstrap protection and rolls back the new account', function () {
    $existing = User::factory()->create();
    $existing->givePermissionTo(Permission::findOrCreate('access-dashboard', 'web'));
    expect(fn () => $this->seed(AdminSeeder::class))->toThrow(ValidationException::class);
    $this->assertDatabaseMissing('users', ['email' => 'admin@example.test']);
    expect($existing->fresh()->getAllPermissions())->toHaveCount(1);
});

<?php

use App\AccountStatus;
use App\Models\PersonalWird;
use App\Models\User;
use App\Models\WorshipIdentity;
use App\Services\AdminUserService;
use Database\Seeders\ContentPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

/** Create an operator holding exactly the selected seeded permissions. */
function userAdministrator(array $permissions = ['access-dashboard', 'view-users', 'manage-users', 'manage-roles', 'manage-content']): User
{
    (new ContentPermissionSeeder)->run();
    $user = User::factory()->create()->fresh();
    $user->givePermissionTo($permissions);

    return $user->fresh();
}

/** @return array<string, mixed> Valid synthetic profile with a non-production password. */
function adminUserPayload(array $overrides = []): array
{
    return array_replace(['name' => 'Test Account', 'email' => 'created@example.test', 'age' => 25,
        'password' => 'Test-password-123!', 'password_confirmation' => 'Test-password-123!'], $overrides);
}

test('user management rejects guests and mobile tokens with 401', function (bool $mobile) {
    $target = User::factory()->create();
    if ($mobile) {
        $admin = userAdministrator();
        $this->withToken($admin->createToken('mobile', ['mobile'])->plainTextToken);
    }
    $this->getJson('/api/v1/admin/users/'.$target->id)->assertUnauthorized();
    $this->postJson('/api/v1/admin/users', adminUserPayload())->assertUnauthorized();
    $this->patchJson('/api/v1/admin/users/'.$target->id, ['name' => 'Changed'])->assertUnauthorized();
    $this->putJson('/api/v1/admin/users/'.$target->id.'/roles', ['role_ids' => []])->assertUnauthorized();
    $this->deleteJson('/api/v1/admin/users/'.$target->id)->assertUnauthorized();
})->with([false, true]);

test('view users permission does not grant account mutation or role assignment', function () {
    $admin = userAdministrator(['access-dashboard', 'view-users']);
    $target = User::factory()->create();
    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/users/'.$target->id)->assertOk();
    $this->postJson('/api/v1/admin/users', adminUserPayload())->assertForbidden();
    $this->patchJson('/api/v1/admin/users/'.$target->id, ['name' => 'Changed'])->assertForbidden();
    $this->putJson('/api/v1/admin/users/'.$target->id.'/roles', ['role_ids' => []])->assertForbidden();
    $this->deleteJson('/api/v1/admin/users/'.$target->id)->assertForbidden();
});

test('account creation hashes passwords and returns only explicit profile and role fields', function () {
    $admin = userAdministrator();
    $response = $this->actingAs($admin, 'web')->postJson('/api/v1/admin/users', adminUserPayload(['email' => '  CREATED@EXAMPLE.TEST  ']));
    $response->assertCreated()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.email', 'created@example.test')->assertJsonPath('data.account_status', 'active')->assertJsonPath('data.roles', []);
    $user = User::query()->where('email', 'created@example.test')->firstOrFail();
    expect(Hash::check('Test-password-123!', $user->password))->toBeTrue();
    expect($user->getAllPermissions())->toHaveCount(0);
    expect($user->email_verified_at)->toBeNull();
    expect(array_keys($response->json('data')))->toBe(['id', 'name', 'email', 'account_status', 'age', 'phone', 'roles', 'created_at']);
});

test('account edits preserve omitted password and accept bounded profile fields', function () {
    $admin = userAdministrator();
    $target = User::factory()->create();
    $password = $target->password;
    $this->actingAs($admin, 'web')->patchJson('/api/v1/admin/users/'.$target->id, ['name' => 'Edited', 'phone' => '+201234567890'])
        ->assertOk()->assertJsonPath('data.name', 'Edited')->assertJsonPath('data.phone', '+201234567890');
    expect($target->fresh()->password)->toBe($password);
    $this->putJson('/api/v1/admin/users/'.$target->id, ['name' => 'Full edit', 'email' => $target->email, 'age' => 30, 'phone' => null])
        ->assertOk()->assertJsonPath('data.age', 30)->assertJsonPath('data.phone', null);
});

test('credential and status edits revoke tokens sessions and password reset proofs', function (array $changes) {
    config(['session.driver' => 'database']);
    $admin = userAdministrator();
    $target = User::factory()->create();
    $target->createToken('mobile', ['mobile']);
    DB::table('sessions')->insert(['id' => 'target-session', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);
    DB::table('sessions')->insert(['id' => 'other-session', 'user_id' => $admin->id, 'payload' => '', 'last_activity' => time()]);
    $proof = Password::broker()->createToken($target);
    $email = $target->email;

    $this->actingAs($admin, 'web')->patchJson('/api/v1/admin/users/'.$target->id, $changes)->assertOk();

    expect($target->fresh()->tokens()->count())->toBe(0);
    $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
    $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $email]);
    if (isset($changes['email'])) {
        expect($target->fresh()->email_verified_at)->toBeNull();
    }
    if (isset($changes['password'])) {
        expect(Hash::check($changes['password'], $target->fresh()->password))->toBeTrue();
    }
})->with([
    [['email' => 'changed@example.test']],
    [['account_status' => 'suspended']],
    [['password' => 'Replacement-password-123!', 'password_confirmation' => 'Replacement-password-123!']],
]);

test('role replacement requires both user and role management permissions', function (array $permissions) {
    $admin = userAdministrator($permissions);
    $target = User::factory()->create();
    $this->actingAs($admin, 'web')->putJson('/api/v1/admin/users/'.$target->id.'/roles', ['role_ids' => []])->assertForbidden();
})->with([
    [['access-dashboard', 'manage-users']],
    [['access-dashboard', 'manage-roles']],
]);

test('role assignment replaces roles preserves direct permissions and revokes target credentials', function () {
    $admin = userAdministrator();
    $target = User::factory()->create();
    $target->givePermissionTo('view-users');
    $editor = Role::findOrCreate('Editor', 'web');
    $editor->givePermissionTo('manage-content');
    $target->createToken('mobile', ['mobile']);

    $this->actingAs($admin, 'web')->putJson('/api/v1/admin/users/'.$target->id.'/roles', ['role_ids' => [$editor->id]])
        ->assertOk()->assertJsonPath('data.roles.0.id', $editor->id);

    expect($target->fresh()->hasRole($editor))->toBeTrue();
    expect($target->fresh()->can('manage-content'))->toBeTrue();
    expect($target->fresh()->hasDirectPermission('view-users'))->toBeTrue();
    expect($target->fresh()->tokens()->count())->toBe(0);
    $this->putJson('/api/v1/admin/users/'.$target->id.'/roles', ['role_ids' => []])->assertOk()->assertJsonPath('data.roles', []);
    expect($target->fresh()->can('manage-content'))->toBeFalse();
    expect($target->fresh()->hasDirectPermission('view-users'))->toBeTrue();
});

test('role assignment cannot grant a permission beyond the caller with 403', function () {
    $admin = userAdministrator();
    $target = User::factory()->create();
    $role = Role::findOrCreate('Publisher', 'web');
    $role->givePermissionTo('publish-content');
    $this->actingAs($admin, 'web')->putJson('/api/v1/admin/users/'.$target->id.'/roles', ['role_ids' => [$role->id]])->assertForbidden();
    expect($target->fresh()->roles()->count())->toBe(0);
});

test('self editing deleting and role replacement are forbidden with 403', function () {
    $admin = userAdministrator();
    $this->actingAs($admin, 'web')->patchJson('/api/v1/admin/users/'.$admin->id, ['account_status' => 'suspended'])->assertForbidden();
    $this->putJson('/api/v1/admin/users/'.$admin->id.'/roles', ['role_ids' => []])->assertForbidden();
    $this->deleteJson('/api/v1/admin/users/'.$admin->id)->assertForbidden();
    expect($admin->fresh()->account_status)->toBe(AccountStatus::Active);
});

test('accounts with privileges beyond the caller cannot be changed deleted or reassigned', function (bool $viaRole) {
    $admin = userAdministrator();
    $target = User::factory()->create();
    if ($viaRole) {
        $role = Role::findOrCreate('Publisher', 'web');
        $role->givePermissionTo('publish-content');
        $target->assignRole($role);
    } else {
        $target->givePermissionTo('publish-content');
    }
    $this->actingAs($admin, 'web')->patchJson('/api/v1/admin/users/'.$target->id, ['name' => 'Changed'])->assertForbidden();
    $this->putJson('/api/v1/admin/users/'.$target->id.'/roles', ['role_ids' => []])->assertForbidden();
    $this->deleteJson('/api/v1/admin/users/'.$target->id)->assertForbidden();
    $this->assertModelExists($target);
})->with([false, true]);

test('deleting a user removes owned records credentials and role pivots but preserves other users', function () {
    $admin = userAdministrator();
    $target = User::factory()->create();
    $identity = WorshipIdentity::factory()->registered($target)->create();
    $wird = PersonalWird::factory()->for($identity)->create();
    $role = Role::findOrCreate('Reader', 'web');
    $target->assignRole($role);
    $target->givePermissionTo('view-users');
    $target->createToken('mobile', ['mobile']);

    $this->actingAs($admin, 'web')->deleteJson('/api/v1/admin/users/'.$target->id)->assertNoContent();

    $this->assertModelMissing($target);
    $this->assertModelMissing($identity);
    $this->assertModelMissing($wird);
    $this->assertDatabaseMissing('model_has_roles', ['model_id' => $target->id]);
    $this->assertDatabaseMissing('model_has_permissions', ['model_id' => $target->id]);
    $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $target->id]);
    $this->assertModelExists($admin);
    $this->assertModelExists($role);
});

test('user routes reject malformed or missing IDs with 404', function (string $id) {
    $admin = userAdministrator();
    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/users/'.$id)->assertNotFound();
    $this->patchJson('/api/v1/admin/users/'.$id, ['name' => 'Changed'])->assertNotFound();
    $this->putJson('/api/v1/admin/users/'.$id.'/roles', ['role_ids' => []])->assertNotFound();
    $this->deleteJson('/api/v1/admin/users/'.$id)->assertNotFound();
})->with(['0', '-1', 'abc', '999999']);

test('invalid account input and protected fields return 422', function (array $changes, string $field) {
    $admin = userAdministrator();
    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/users', adminUserPayload($changes))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseMissing('users', ['email' => 'created@example.test']);
})->with([
    [['name' => ''], 'name'], [['name' => str_repeat('x', 161)], 'name'], [['name' => '<script>bad</script>'], 'name'],
    [['email' => 'invalid'], 'email'], [['age' => 0], 'age'], [['age' => 121], 'age'],
    [['phone' => '123'], 'phone'], [['account_status' => 'admin'], 'account_status'],
    [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    [['password' => str_repeat('x', 73), 'password_confirmation' => str_repeat('x', 73)], 'password'],
    [['password_confirmation' => 'mismatch'], 'password'], [['google_id' => '123'], 'google_id'],
    [['roles' => [1]], 'roles'], [['permissions' => ['manage-users']], 'permissions'],
    [['email_verified_at' => '2026-10-23'], 'email_verified_at'],
]);

test('empty edits and duplicate emails return 422 without changing accounts', function () {
    $admin = userAdministrator();
    $target = User::factory()->create();
    $this->actingAs($admin, 'web')->patchJson('/api/v1/admin/users/'.$target->id, [])->assertUnprocessable()->assertJsonValidationErrors('user');
    $this->patchJson('/api/v1/admin/users/'.$target->id, ['email' => $admin->email])->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->postJson('/api/v1/admin/users', adminUserPayload(['email' => $target->email]))->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('role replacement rejects unsupported malformed duplicate and other guard roles with 422', function (array $payload, string $field) {
    $admin = userAdministrator();
    $target = User::factory()->create();
    $role = Role::findOrCreate('Other', 'other');
    if ($payload === ['other']) {
        $payload = ['role_ids' => [$role->id]];
    }
    $this->actingAs($admin, 'web')->putJson('/api/v1/admin/users/'.$target->id.'/roles', $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($target->fresh()->roles()->count())->toBe(0);
})->with([
    [[], 'role_ids'], [['role_ids' => '1'], 'role_ids'], [['role_ids' => [999999]], 'role_ids.0'],
    [['role_ids' => [1, 1]], 'role_ids.0'], [['role_ids' => [], 'permissions' => []], 'permissions'],
    [['other'], 'role_ids.0'],
]);

test('all user mutation endpoints require CSRF for browser sessions', function () {
    $admin = userAdministrator();
    $target = User::factory()->create();
    $this->app->instance('env', 'local');
    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/users', adminUserPayload())->assertStatus(419);
    $this->patchJson('/api/v1/admin/users/'.$target->id, ['name' => 'Changed'])->assertStatus(419);
    $this->putJson('/api/v1/admin/users/'.$target->id.'/roles', ['role_ids' => []])->assertStatus(419);
    $this->deleteJson('/api/v1/admin/users/'.$target->id)->assertStatus(419);
    $this->withSession(['_token' => 'known'])->withHeader('X-CSRF-TOKEN', 'known')
        ->postJson('/api/v1/admin/users', adminUserPayload())->assertCreated();
});

test('user mutation endpoints share the admin rate limit', function () {
    $admin = userAdministrator();
    $target = User::factory()->create();
    $this->actingAs($admin, 'web');
    for ($attempt = 0; $attempt < 120; $attempt++) {
        $this->getJson('/api/v1/admin/users/'.$target->id)->assertOk();
    }
    $this->patchJson('/api/v1/admin/users/'.$target->id, ['name' => 'Changed'])->assertTooManyRequests();
});

test('suspended operators cannot manage users with 403', function () {
    $admin = userAdministrator();
    $admin->account_status = AccountStatus::Suspended;
    $admin->save();
    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/users', adminUserPayload())->assertForbidden();
    $this->assertDatabaseMissing('users', ['email' => 'created@example.test']);
});

test('a user manager without view users permission cannot read account details', function () {
    $admin = userAdministrator(['access-dashboard', 'manage-users']);
    $target = User::factory()->create();
    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/users/'.$target->id)->assertForbidden();
});

test('stale role references rejected under the write lock leave current assignments unchanged', function () {
    $admin = userAdministrator();
    $target = User::factory()->create();
    $role = Role::findOrCreate('Reader', 'web');
    $target->assignRole($role);
    expect(fn () => app(AdminUserService::class)->roles($admin, $target, [999999]))
        ->toThrow(ValidationException::class);
    expect($target->fresh()->hasRole($role))->toBeTrue();
});

test('stale actor privileges are rechecked before any account write', function () {
    $admin = userAdministrator();
    $target = User::factory()->create();
    $revoked = $admin->fresh();
    $revoked->revokePermissionTo('manage-users');
    expect(fn () => app(AdminUserService::class)->save($admin, ['name' => 'Changed'], $target))
        ->toThrow(HttpException::class);
    expect($target->fresh()->name)->toBe($target->name);
});

test('user creation accepts multiple authorized web roles and returns their assignments', function () {
    $admin = userAdministrator();
    $editor = Role::findOrCreate('Editor', 'web');
    $editor->givePermissionTo('manage-content');
    $reader = Role::findOrCreate('Reader', 'web');
    $reader->givePermissionTo('view-users');

    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/users', adminUserPayload(['role_ids' => [$editor->id, $reader->id]]))
        ->assertCreated()->assertJsonCount(2, 'data.roles')->assertJsonPath('data.roles.0.id', $editor->id);

    $target = User::query()->where('email', 'created@example.test')->firstOrFail();
    expect($target->hasRole($editor))->toBeTrue();
    expect($target->hasRole($reader))->toBeTrue();
    expect($target->can('manage-content'))->toBeTrue();
    expect($target->permissions()->count())->toBe(0);
});

test('user creation without role management can omit roles but cannot submit a role array', function () {
    $admin = userAdministrator(['access-dashboard', 'manage-users']);
    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/users', adminUserPayload(['role_ids' => []]))->assertForbidden();
    $this->assertDatabaseMissing('users', ['email' => 'created@example.test']);
    $this->postJson('/api/v1/admin/users', adminUserPayload())->assertCreated()->assertJsonPath('data.roles', []);
});

test('user creation accepts an explicit empty role array for authorized role managers', function () {
    $admin = userAdministrator();
    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/users', adminUserPayload(['role_ids' => []]))
        ->assertCreated()->assertJsonPath('data.roles', []);
});

test('user creation with unheld role permissions rolls back the new account with 403', function () {
    $admin = userAdministrator();
    $role = Role::findOrCreate('Publisher', 'web');
    $role->givePermissionTo('publish-content');

    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/users', adminUserPayload(['role_ids' => [$role->id]]))->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'created@example.test']);
    $this->assertDatabaseCount('model_has_roles', 0);
});

test('creation rejects unavailable duplicate malformed and non web roles with 422', function (array $payload, string $field) {
    $admin = userAdministrator();
    $role = Role::findOrCreate('Other', 'other');
    if ($payload === ['other']) {
        $payload = ['role_ids' => [$role->id]];
    }
    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/users', adminUserPayload($payload))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseMissing('users', ['email' => 'created@example.test']);
})->with([
    [['role_ids' => null], 'role_ids'], [['role_ids' => '1'], 'role_ids'],
    [['role_ids' => [999999]], 'role_ids.0'], [['role_ids' => [1, 1]], 'role_ids.0'],
    [['role_ids' => ['a' => 1]], 'role_ids'], [['other'], 'role_ids.0'],
]);

test('roles disappearing before transactional creation leave no new account', function () {
    $admin = userAdministrator();
    expect(fn () => app(AdminUserService::class)->save($admin, adminUserPayload(['role_ids' => [999999]])))
        ->toThrow(ValidationException::class);
    $this->assertDatabaseMissing('users', ['email' => 'created@example.test']);
});

test('profile updates still reject inline role assignment', function () {
    $admin = userAdministrator();
    $target = User::factory()->create();
    $this->actingAs($admin, 'web')->patchJson('/api/v1/admin/users/'.$target->id, ['role_ids' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('role_ids');
});

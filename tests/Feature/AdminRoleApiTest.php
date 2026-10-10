<?php

use App\AccountStatus;
use App\Models\User;
use Database\Seeders\ContentPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

/** Create an administrator holding only the explicit permission set. */
function roleAdministrator(array $permissions = ['access-dashboard', 'manage-roles', 'manage-content']): User
{
    $user = User::factory()->create();
    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user->fresh();
}

test('role and permission endpoints require authentication with 401', function (string $endpoint) {
    $this->getJson('/api/v1/admin/'.$endpoint)->assertUnauthorized();
})->with(['roles', 'permissions']);

test('role writes reject mobile bearer authentication with 401', function () {
    $admin = roleAdministrator();
    $this->withToken($admin->createToken('mobile', ['mobile'])->plainTextToken)
        ->postJson('/api/v1/admin/roles', ['name' => 'Editor', 'permissions' => []])->assertUnauthorized();
    $this->assertDatabaseCount('roles', 0);
});

test('role management requires dashboard and manage roles permissions with 403', function (array $permissions) {
    $admin = roleAdministrator($permissions);
    $role = Role::findOrCreate('Editor', 'web');
    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/roles')->assertForbidden();
    $this->getJson('/api/v1/admin/permissions')->assertForbidden();
    $this->getJson('/api/v1/admin/roles/'.$role->id)->assertForbidden();
    $this->postJson('/api/v1/admin/roles', ['name' => 'Other', 'permissions' => []])->assertForbidden();
    $this->patchJson('/api/v1/admin/roles/'.$role->id, ['name' => 'Other'])->assertForbidden();
    $this->deleteJson('/api/v1/admin/roles/'.$role->id)->assertForbidden();
    expect($role->fresh()->name)->toBe('Editor');
})->with([
    'dashboard only' => [['access-dashboard']],
    'role manager without dashboard' => [['manage-roles']],
]);

test('suspended role managers are forbidden with 403', function () {
    $admin = roleAdministrator();
    $admin->account_status = AccountStatus::Suspended;
    $admin->save();
    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/roles')->assertForbidden();
});

test('role CRUD syncs explicit permissions and invalidates permission cache', function () {
    $admin = roleAdministrator();
    $this->actingAs($admin, 'web');
    $id = $this->postJson('/api/v1/admin/roles', ['name' => 'Editor', 'permissions' => ['manage-content']])
        ->assertCreated()->assertJsonPath('data.name', 'Editor')
        ->assertJsonPath('data.permissions', ['manage-content'])->json('data.id');
    $role = Role::findOrFail($id);
    expect($role->hasPermissionTo('manage-content'))->toBeTrue();
    $this->getJson('/api/v1/admin/roles/'.$id)->assertOk()->assertJsonPath('data.id', $id);
    $this->patchJson('/api/v1/admin/roles/'.$id, ['name' => 'Reviewer'])
        ->assertOk()->assertJsonPath('data.permissions', ['manage-content']);
    $member = User::factory()->create();
    $member->assignRole($role);
    expect($member->fresh()->can('manage-content'))->toBeTrue();
    $this->putJson('/api/v1/admin/roles/'.$id, ['name' => 'Reviewer', 'permissions' => []])
        ->assertOk()->assertJsonPath('data.permissions', []);
    expect($member->fresh()->can('manage-content'))->toBeFalse();
    $member->removeRole($role);
    $this->deleteJson('/api/v1/admin/roles/'.$id)->assertNoContent();
    $this->assertDatabaseMissing('roles', ['id' => $id]);
    $this->assertDatabaseMissing('role_has_permissions', ['role_id' => $id]);
});

test('role managers cannot grant permissions they do not hold with 403', function () {
    $admin = roleAdministrator();
    Permission::findOrCreate('publish-content', 'web');
    $role = Role::findOrCreate('Editor', 'web');
    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/roles', ['name' => 'Publisher', 'permissions' => ['publish-content']])->assertForbidden();
    $this->patchJson('/api/v1/admin/roles/'.$role->id, ['name' => 'Publisher', 'permissions' => ['publish-content']])->assertForbidden();
    $this->assertDatabaseMissing('roles', ['name' => 'Publisher']);
    expect($role->fresh()->permissions)->toHaveCount(0);
});

test('roles containing privileges beyond the caller cannot be changed or deleted with 403', function () {
    $admin = roleAdministrator();
    $permission = Permission::findOrCreate('publish-content', 'web');
    $role = Role::findOrCreate('Publisher', 'web');
    $role->givePermissionTo($permission);
    $this->actingAs($admin, 'web')->patchJson('/api/v1/admin/roles/'.$role->id, ['permissions' => []])->assertForbidden();
    $this->deleteJson('/api/v1/admin/roles/'.$role->id)->assertForbidden();
    expect($role->fresh()->hasPermissionTo($permission))->toBeTrue();
});

test('administrators cannot change or delete their own assigned roles with 403', function () {
    $admin = roleAdministrator();
    $role = Role::findOrCreate('Own role', 'web');
    $role->givePermissionTo('manage-roles');
    $admin->assignRole($role);
    $this->actingAs($admin->fresh(), 'web')->patchJson('/api/v1/admin/roles/'.$role->id, ['permissions' => []])->assertForbidden();
    $this->deleteJson('/api/v1/admin/roles/'.$role->id)->assertForbidden();
    expect($role->fresh()->hasPermissionTo('manage-roles'))->toBeTrue();
});

test('assigned roles cannot be deleted with 409', function () {
    $admin = roleAdministrator();
    $role = Role::findOrCreate('Editor', 'web');
    $member = User::factory()->create();
    $member->assignRole($role);
    $this->actingAs($admin, 'web')->deleteJson('/api/v1/admin/roles/'.$role->id)->assertConflict();
    expect($member->fresh()->hasRole($role))->toBeTrue();
});

test('role and permission catalogs are paginated and hide non web guards', function () {
    $admin = roleAdministrator();
    Role::findOrCreate('Hidden', 'other');
    Permission::findOrCreate('hidden', 'other');
    $first = Role::findOrCreate('First', 'web');
    Role::findOrCreate('Second', 'web');
    $response = $this->actingAs($admin, 'web')->getJson('/api/v1/admin/roles?per_page=1');
    $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.id', $first->id);
    expect(array_keys($response->json('data.0')))->toBe(['id', 'name', 'permissions']);
    $this->getJson('/api/v1/admin/permissions?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('total', 3);
});

test('other guard and malformed role references return 404', function (string $reference) {
    $admin = roleAdministrator();
    $role = Role::findOrCreate('Hidden', 'other');
    $id = $reference === 'other' ? $role->id : $reference;
    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/roles/'.$id)->assertNotFound();
    $this->patchJson('/api/v1/admin/roles/'.$id, ['name' => 'Changed'])->assertNotFound();
    $this->deleteJson('/api/v1/admin/roles/'.$id)->assertNotFound();
})->with(['other', '0', '-1', 'abc', '999999']);

test('role writes reject invalid and unsupported inputs with 422', function (array $input, string $field) {
    $admin = roleAdministrator();
    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/roles', $input)
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('roles', 0);
})->with([
    [['permissions' => []], 'name'],
    [['name' => '   ', 'permissions' => []], 'name'],
    [['name' => str_repeat('x', 101), 'permissions' => []], 'name'],
    [['name' => '<script>bad</script>', 'permissions' => []], 'name'],
    [['name' => 'Editor'], 'permissions'],
    [['name' => 'Editor', 'permissions' => 'manage-content'], 'permissions'],
    [['name' => 'Editor', 'permissions' => ['a' => 'manage-content']], 'permissions'],
    [['name' => 'Editor', 'permissions' => ['missing']], 'permissions.0'],
    [['name' => 'Editor', 'permissions' => ['manage-content', 'manage-content']], 'permissions.0'],
    [['name' => 'Editor', 'permissions' => [], 'guard_name' => 'web'], 'guard_name'],
    [['name' => 'Editor', 'permissions' => [], 'users' => [1]], 'users'],
]);

test('duplicate names and empty patches are rejected with 422', function () {
    $admin = roleAdministrator();
    $role = Role::findOrCreate('Editor', 'web');
    $this->actingAs($admin, 'web')->postJson('/api/v1/admin/roles', ['name' => 'Editor', 'permissions' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->patchJson('/api/v1/admin/roles/'.$role->id, [])->assertUnprocessable()->assertJsonValidationErrors('role');
    $this->putJson('/api/v1/admin/roles/'.$role->id, ['name' => 'Editor'])->assertUnprocessable()->assertJsonValidationErrors('permissions');
});

test('role catalogs reject invalid pagination and query injection with 422', function (string $endpoint, string $query, string $field) {
    $admin = roleAdministrator();
    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/'.$endpoint.'?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with(['roles', 'permissions'])->with([
    ['page=0', 'page'], ['per_page=101', 'per_page'], ['page=abc', 'page'], ['sort=name', 'sort'],
]);

test('role writes require CSRF even for same origin browser requests', function () {
    $admin = roleAdministrator();
    $this->app->instance('env', 'local');
    $this->actingAs($admin, 'web')->withHeader('Sec-Fetch-Site', 'same-origin')
        ->postJson('/api/v1/admin/roles', ['name' => 'Editor', 'permissions' => []])->assertStatus(419);
    $this->withSession(['_token' => 'known'])->withHeader('X-CSRF-TOKEN', 'known')
        ->postJson('/api/v1/admin/roles', ['name' => 'Editor', 'permissions' => []])->assertCreated();
});

test('role management is limited to 120 requests per authenticated administrator', function () {
    $admin = roleAdministrator();
    $this->actingAs($admin, 'web');
    for ($attempt = 0; $attempt < 120; $attempt++) {
        $this->getJson('/api/v1/admin/roles')->assertOk();
    }
    $this->getJson('/api/v1/admin/permissions')->assertTooManyRequests()->assertHeader('Retry-After');
});

test('role management permission is seeded without granting privileges', function () {
    $user = User::factory()->create();
    $this->seed(ContentPermissionSeeder::class);
    $this->seed(ContentPermissionSeeder::class);
    expect(Permission::query()->where('name', 'manage-roles')->count())->toBe(1);
    expect($user->fresh()->can('manage-roles'))->toBeFalse();
});

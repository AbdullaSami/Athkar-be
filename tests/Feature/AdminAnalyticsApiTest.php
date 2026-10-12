<?php

use App\AccountStatus;
use App\Models\Category;
use App\Models\Dhikr;
use App\Models\Subcategory;
use App\Models\User;
use App\Models\WorshipIdentity;
use Database\Seeders\ContentPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

/** Create an administrator with only explicitly selected permissions. */
function analyticsAdministrator(array $permissions = ['access-dashboard', 'view-analytics']): User
{
    $user = User::factory()->create();
    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user->fresh();
}

test('analytics rejects unauthenticated requests with 401', function () {
    $this->getJson('/api/v1/admin/analytics')->assertUnauthorized();
});

test('analytics rejects mobile administrator tokens with 401', function () {
    $admin = analyticsAdministrator();
    $this->withToken($admin->createToken('mobile', ['mobile'])->plainTextToken)
        ->getJson('/api/v1/admin/analytics')->assertUnauthorized();
});

test('analytics requires dashboard and explicit analytics permissions with 403', function (array $permissions) {
    $admin = analyticsAdministrator($permissions);
    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/analytics')->assertForbidden();
})->with([
    'dashboard only' => [['access-dashboard']],
    'analytics only' => [['view-analytics']],
    'user overview only' => [['access-dashboard', 'view-users']],
    'content manager' => [['access-dashboard', 'manage-content']],
]);

test('analytics refuses suspended administrators with 403', function () {
    $admin = analyticsAdministrator();
    $admin->account_status = AccountStatus::Suspended;
    $admin->save();
    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/analytics')->assertForbidden();
});

test('analytics returns zero counts for missing content and guests', function () {
    $this->freezeTime();
    $admin = analyticsAdministrator();
    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/analytics')->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['data' => [
            'generated_at' => now()->toIso8601String(), 'scope' => 'current_records',
            'accounts' => ['registered_accounts' => 1, 'guest_identities' => 0, 'total_accounts_and_guest_identities' => 1, 'active_accounts' => 1, 'suspended_accounts' => 0],
            'content' => ['categories' => 0, 'subcategories' => 0, 'dhikrs' => ['total' => 0, 'draft' => 0, 'published' => 0]],
        ]]);
});

test('analytics counts current accounts and content without duplicating claimed identities', function () {
    $this->freezeTime();
    $admin = analyticsAdministrator();
    User::factory()->create(['account_status' => AccountStatus::Suspended]);
    WorshipIdentity::factory()->registered($admin)->count(2)->create();
    WorshipIdentity::factory()->count(3)->create();
    $category = Category::factory()->create();
    $subcategory = Subcategory::factory()->for($category)->create();
    Subcategory::factory()->for($category)->create();
    Dhikr::factory()->for($subcategory)->count(2)->create();
    $published = Dhikr::factory()->for($subcategory)->published()->create();

    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/analytics')->assertOk()
        ->assertExactJson(['data' => [
            'generated_at' => now()->toIso8601String(), 'scope' => 'current_records',
            'accounts' => ['registered_accounts' => 2, 'guest_identities' => 3, 'total_accounts_and_guest_identities' => 5, 'active_accounts' => 1, 'suspended_accounts' => 1],
            'content' => ['categories' => 1, 'subcategories' => 2, 'dhikrs' => ['total' => 3, 'draft' => 2, 'published' => 1]],
        ]]);

    $published->delete();
    $this->getJson('/api/v1/admin/analytics')->assertOk()->assertJsonPath('data.content.dhikrs', ['total' => 2, 'draft' => 2, 'published' => 0]);
});

test('analytics rejects unsupported filters with 422', function (string $query, string $field) {
    $admin = analyticsAdministrator();
    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/analytics?'.$query)
        ->assertUnprocessable()->assertJsonPath('errors.'.$field.'.0', 'Unsupported field.');
})->with([['user_id=1', 'user_id'], ['start_date=2026-01-01', 'start_date'], ['sort=email', 'sort'], ['page=1', 'page']]);

test('analytics uses the 120 request administrator rate limit', function () {
    $admin = analyticsAdministrator();
    $this->actingAs($admin, 'web');
    for ($attempt = 0; $attempt < 120; $attempt++) {
        $this->getJson('/api/v1/admin/analytics')->assertOk();
    }
    $this->getJson('/api/v1/admin/analytics')->assertTooManyRequests()->assertHeader('Retry-After');
});

test('analytics permission is seeded idempotently without granting access', function () {
    $admin = analyticsAdministrator(['access-dashboard']);
    $this->seed(ContentPermissionSeeder::class);
    $this->seed(ContentPermissionSeeder::class);
    expect(Permission::query()->where('name', 'view-analytics')->where('guard_name', 'web')->count())->toBe(1);
    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/analytics')->assertForbidden();
});

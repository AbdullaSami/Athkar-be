<?php

use App\AccountStatus;
use App\Models\User;
use App\Models\WorshipIdentity;
use App\Services\GuestIdentityService;
use Database\Seeders\ContentPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

/** Create an administrator with only the permissions needed by this test. */
function overviewAdministrator(array $permissions = ['access-dashboard', 'view-users']): User
{
    $user = User::factory()->create();
    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user->fresh();
}

test('overview endpoints reject unauthenticated requests with 401', function (string $endpoint) {
    $this->getJson('/api/v1/admin/'.$endpoint)->assertUnauthorized();
})->with(['user-overview', 'users', 'guests']);

test('overview endpoints reject mobile bearer tokens with 401', function (string $endpoint) {
    $admin = overviewAdministrator();

    $this->withToken($admin->createToken('mobile', ['mobile'])->plainTextToken)
        ->getJson('/api/v1/admin/'.$endpoint)->assertUnauthorized();
})->with(['user-overview', 'users', 'guests']);

test('overview endpoints require both dashboard and user overview permission with 403', function (string $endpoint, array $permissions) {
    $admin = overviewAdministrator($permissions);

    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/'.$endpoint)->assertForbidden();
})->with(['user-overview', 'users', 'guests'])->with([
    'dashboard only' => [['access-dashboard']],
    'overview only' => [['view-users']],
]);

test('suspended administrators cannot access overview endpoints', function (string $endpoint) {
    $admin = overviewAdministrator();
    $admin->account_status = AccountStatus::Suspended;
    $admin->save();

    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/'.$endpoint)->assertForbidden();
})->with(['user-overview', 'users', 'guests']);

test('overview counts accounts once and excludes claimed identities from guests', function () {
    $admin = overviewAdministrator();
    WorshipIdentity::factory()->registered($admin)->count(2)->create();
    $guest = app(GuestIdentityService::class)->create();
    WorshipIdentity::factory()->create();
    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/user-overview')
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['data' => ['registered_accounts' => 1, 'guest_identities' => 2, 'total_accounts_and_guest_identities' => 3]]);

    app(GuestIdentityService::class)->claim($admin, $guest['guest_token']);

    $this->getJson('/api/v1/admin/user-overview')->assertOk()
        ->assertExactJson(['data' => ['registered_accounts' => 1, 'guest_identities' => 1, 'total_accounts_and_guest_identities' => 2]]);
});

test('registered users are paginated in descending ID order with only overview fields', function () {
    $admin = overviewAdministrator();
    $user = User::factory()->create(['account_status' => AccountStatus::Suspended]);

    $response = $this->actingAs($admin, 'web')->getJson('/api/v1/admin/users?per_page=1');

    $response->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.per_page', 1)->assertJsonPath('data.0.id', $user->id)
        ->assertJsonPath('data.0.account_status', 'suspended');
    expect(array_keys($response->json('data.0')))->toBe(['id', 'name', 'email', 'account_status', 'created_at']);
    $this->getJson('/api/v1/admin/users?per_page=1&page=2')->assertOk()->assertJsonPath('data.0.id', $admin->id);
});

test('guest lists exclude claimed identities and expose only anonymous overview fields', function () {
    $admin = overviewAdministrator();
    WorshipIdentity::factory()->registered($admin)->create();
    $older = WorshipIdentity::factory()->create();
    $newer = WorshipIdentity::factory()->create();

    $response = $this->actingAs($admin, 'web')->getJson('/api/v1/admin/guests?per_page=1');

    $response->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.identity_id', $newer->public_id);
    expect(array_keys($response->json('data.0')))->toBe(['identity_id', 'created_at']);
    $this->getJson('/api/v1/admin/guests?per_page=1&page=2')->assertOk()->assertJsonPath('data.0.identity_id', $older->public_id);
});

test('overview lists use bounded default pagination and return empty pages safely', function (string $endpoint) {
    $admin = overviewAdministrator();

    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/'.$endpoint)
        ->assertOk()->assertJsonPath('meta.per_page', 20);
    $this->getJson('/api/v1/admin/'.$endpoint.'?page=1000000&per_page=100')
        ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.per_page', 100);
})->with(['users', 'guests']);

test('overview lists reject invalid pagination and unsupported query fields with 422', function (string $endpoint, string $query, string $field) {
    $admin = overviewAdministrator();

    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/'.$endpoint.'?'.$query)
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with(['users', 'guests'])->with([
    ['page=0', 'page'], ['page=1000001', 'page'], ['page=abc', 'page'], ['page[]=1', 'page'],
    ['per_page=0', 'per_page'], ['per_page=101', 'per_page'], ['per_page=1.5', 'per_page'],
    ['sort=password', 'sort'], ['user_id=1', 'user_id'],
]);

test('overview totals reject unexpected input with 422', function () {
    $admin = overviewAdministrator();

    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/user-overview?page=1')
        ->assertUnprocessable()->assertJsonPath('errors.page.0', 'Unsupported field.');
});

test('overview endpoints share the authenticated admin limit of 120 requests per minute', function () {
    $admin = overviewAdministrator();
    $this->actingAs($admin, 'web');
    for ($attempt = 0; $attempt < 120; $attempt++) {
        $this->getJson('/api/v1/admin/user-overview')->assertOk();
    }

    $this->getJson('/api/v1/admin/users')->assertTooManyRequests()->assertHeader('Retry-After');
    $other = overviewAdministrator();
    Auth::forgetGuards();
    $this->actingAs($other, 'web')->getJson('/api/v1/admin/guests')->assertOk();
});

test('permission seeding defines overview access idempotently without granting it', function () {
    $admin = overviewAdministrator(['access-dashboard']);
    $this->seed(ContentPermissionSeeder::class);
    $this->seed(ContentPermissionSeeder::class);

    expect(Permission::query()->where('name', 'view-users')->where('guard_name', 'web')->count())->toBe(1);
    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/users')->assertForbidden();
});

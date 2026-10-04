<?php

use App\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

/** Grant only dashboard access through the existing role system. */
function grantDashboardAccess(User $user): void
{
    $permission = Permission::findOrCreate('access-dashboard', 'web');
    $role = Role::findOrCreate('dashboard administrator', 'web');
    $role->givePermissionTo($permission);
    $user->assignRole($role);
}

test('dashboard login requires a CSRF token even on same-origin requests', function () {
    $this->app->instance('env', 'local');
    $user = User::factory()->create()->fresh();
    grantDashboardAccess($user);

    $this->withHeader('Sec-Fetch-Site', 'same-origin')
        ->postJson('/api/v1/admin/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertStatus(419)->assertExactJson(['message' => 'CSRF token mismatch.']);

    $this->assertGuest('web');
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('dashboard login rejects an incorrect CSRF token', function () {
    $this->app->instance('env', 'local');

    $this->withSession(['_token' => 'correct'])->withHeader('X-CSRF-TOKEN', 'wrong')
        ->postJson('/api/v1/admin/auth/login', ['email' => 'admin@example.com', 'password' => 'password'])
        ->assertStatus(419);

    $this->assertGuest('web');
});

test('CSRF cookie flow logs an administrator into a regenerated session without issuing tokens', function () {
    $this->app->instance('env', 'local');
    $user = User::factory()->create()->fresh();
    grantDashboardAccess($user);
    $cookieResponse = $this->getJson('/sanctum/csrf-cookie');
    $cookieResponse->assertNoContent()->assertCookie('XSRF-TOKEN');
    $sessionName = config('session.cookie');
    $oldSession = $cookieResponse->getCookie($sessionName)->getValue();
    $xsrf = $cookieResponse->getCookie('XSRF-TOKEN', decrypt: false)->getValue();
    $this->withCookie($sessionName, $oldSession)->withHeader('X-XSRF-TOKEN', $xsrf);

    $response = $this->postJson('/api/v1/admin/auth/login', ['email' => $user->email, 'password' => 'password']);

    $response->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonMissingPath('data.token');
    $this->assertAuthenticatedAs($user, 'web');
    $newSession = $response->getCookie($sessionName)->getValue();
    expect($newSession)->not->toBe($oldSession);
    $this->assertDatabaseCount('personal_access_tokens', 0);
    Auth::forgetGuards();
    $this->withCookie($sessionName, $newSession)->getJson('/api/v1/admin/auth/me')
        ->assertOk()->assertJsonPath('data.id', $user->id);
});

test('dashboard login returns 403 for valid credentials without dashboard permission', function () {
    $user = User::factory()->create()->fresh();

    $this->postJson('/api/v1/admin/auth/login', ['email' => $user->email, 'password' => 'password'])->assertForbidden();

    $this->assertGuest('web');
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('dashboard login returns 422 for invalid or suspended credentials', function (string $kind) {
    $user = User::factory()->create(['account_status' => $kind === 'suspended' ? AccountStatus::Suspended : AccountStatus::Active]);
    grantDashboardAccess($user);

    $this->postJson('/api/v1/admin/auth/login', ['email' => $user->email, 'password' => $kind === 'invalid' ? 'wrong' : 'password'])
        ->assertUnprocessable()->assertJsonPath('errors.email.0', 'The provided credentials are incorrect.');

    $this->assertGuest('web');
})->with(['invalid', 'suspended']);

test('dashboard endpoints return 401 for a guest or a mobile token even with admin permission', function (string $kind) {
    if ($kind === 'mobile') {
        $user = User::factory()->create()->fresh();
        grantDashboardAccess($user);
        $this->withToken($user->createToken('mobile', ['mobile'])->plainTextToken);
    }

    $this->getJson('/api/v1/admin/auth/me')->assertUnauthorized();
})->with(['guest', 'mobile']);

test('dashboard profile enforces permission and current account status', function (string $kind) {
    $user = User::factory()->create(['account_status' => $kind === 'suspended' ? AccountStatus::Suspended : AccountStatus::Active]);
    if ($kind === 'suspended') {
        grantDashboardAccess($user);
    }

    $this->actingAs($user, 'web')->getJson('/api/v1/admin/auth/me')->assertForbidden();
})->with(['no permission', 'suspended']);

test('dashboard logout requires CSRF and preserves mobile credentials', function () {
    $this->app->instance('env', 'local');
    $user = User::factory()->create()->fresh();
    grantDashboardAccess($user);
    $mobile = $user->createToken('mobile', ['mobile']);
    $this->actingAs($user, 'web');

    $this->postJson('/api/v1/admin/auth/logout')->assertStatus(419);

    $this->assertAuthenticatedAs($user, 'web');
    $this->assertModelExists($mobile->accessToken);
});

test('valid dashboard logout invalidates its session and regenerates CSRF', function () {
    $this->app->instance('env', 'local');
    $user = User::factory()->create()->fresh();
    grantDashboardAccess($user);
    $this->actingAs($user, 'web')->withSession(['_token' => 'known-token']);
    $sessionId = $this->app['session']->getId();

    $this->withHeader('X-CSRF-TOKEN', 'known-token')->postJson('/api/v1/admin/auth/logout')->assertNoContent();

    $this->assertGuest('web');
    expect($this->app['session']->getId())->not->toBe($sessionId);
    expect($this->app['session']->token())->not->toBe('known-token');
});

test('dashboard login rejects unsupported device or role fields', function (string $field) {
    $this->postJson('/api/v1/admin/auth/login', ['email' => 'admin@example.com', 'password' => 'password', $field => 'unsupported'])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with(['device_name', 'roles']);

test('dashboard login returns 429 after five attempts', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/v1/admin/auth/login', ['email' => 'admin@example.com', 'password' => 'wrong'])->assertUnprocessable();
    }

    $this->postJson('/api/v1/admin/auth/login', ['email' => 'admin@example.com', 'password' => 'wrong'])
        ->assertTooManyRequests()->assertHeader('Retry-After');
});

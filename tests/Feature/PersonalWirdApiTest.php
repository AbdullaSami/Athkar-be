<?php

use App\Models\PersonalWird;
use App\Models\User;
use App\Models\WorshipIdentity;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

/** @return array{base: string, headers: array<string, string>, identity: WorshipIdentity} A real owner credential. */
function personalWirdActor(string $kind): array
{
    if ($kind === 'guest') {
        $token = Str::random(64);
        $identity = WorshipIdentity::factory()->create(['guest_token_hash' => hash('sha256', $token)]);

        return ['base' => '/api/v1/guest/personal-wirds', 'headers' => ['X-Guest-Token' => $token], 'identity' => $identity];
    }
    $user = User::factory()->create();
    $identity = WorshipIdentity::factory()->registered($user)->create();

    return ['base' => '/api/v1/personal-wirds', 'headers' => ['Authorization' => 'Bearer '.$user->createToken('mobile', ['mobile'])->plainTextToken], 'identity' => $identity];
}

test('guest identity creation stores only a credential hash', function () {
    $response = $this->postJson('/api/v1/guest/identities');

    $response->assertCreated()->assertJsonStructure(['data' => ['identity_id', 'guest_token']]);
    $identity = WorshipIdentity::sole();
    expect($identity->public_id)->toBe($response->json('data.identity_id'));
    expect($identity->guest_token_hash)->toBe(hash('sha256', $response->json('data.guest_token')));
    expect($identity->user_id)->toBeNull();
    expect($response->json('data'))->not->toHaveKeys(['guest_token_hash', 'user_id']);
});

test('guests and accounts can manage Wird while preserving Arabic text and exceeding the target', function (string $kind) {
    $actor = personalWirdActor($kind);
    $this->withHeaders($actor['headers']);
    $text = "  سبحان الله\nالحمد لله  ";

    $response = $this->postJson($actor['base'], ['text' => $text, 'target_count' => 1]);

    $response->assertCreated()->assertJsonPath('data.text', $text)->assertJsonPath('data.current_count', 0);
    $id = $response->json('data.id');
    $this->getJson($actor['base'].'/'.$id)->assertOk()->assertJsonPath('data.text', $text);
    $this->postJson($actor['base'].'/'.$id.'/increment')->assertOk()->assertJsonPath('data.current_count', 1);
    $this->postJson($actor['base'].'/'.$id.'/increment')->assertOk()->assertJsonPath('data.current_count', 2);
    $this->patchJson($actor['base'].'/'.$id, ['target_count' => 3])->assertOk()->assertJsonPath('data.current_count', 2);
    $this->postJson($actor['base'].'/'.$id.'/reset')->assertOk()->assertJsonPath('data.current_count', 0);
    $this->deleteJson($actor['base'].'/'.$id)->assertNoContent();
    $this->assertDatabaseCount('personal_wirds', 0);
})->with(['guest', 'account']);

test('Wird lists are paginated and include only the requesting owners records', function (string $kind) {
    $actor = personalWirdActor($kind);
    PersonalWird::factory()->count(3)->for($actor['identity'])->create();
    PersonalWird::factory()->create();

    $response = $this->withHeaders($actor['headers'])->getJson($actor['base'].'?per_page=2');

    $response->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3)->assertJsonPath('meta.per_page', 2);
    expect($response->json('data.0'))->not->toHaveKeys(['worship_identity_id', 'guest_token_hash']);
})->with(['guest', 'account']);

test('another owners Wird returns 404 for every read and write action', function (string $kind, string $action) {
    $actor = personalWirdActor($kind);
    $other = PersonalWird::factory()->create(['current_count' => 2]);
    $url = $actor['base'].'/'.$other->id;
    $this->withHeaders($actor['headers']);

    $response = match ($action) {
        'show' => $this->getJson($url),
        'update' => $this->patchJson($url, ['text' => 'Changed']),
        'delete' => $this->deleteJson($url),
        default => $this->postJson($url.'/'.$action),
    };

    $response->assertNotFound();
    $this->assertModelExists($other);
    expect($other->fresh()->current_count)->toBe(2);
    expect($other->fresh()->text)->toBe($other->text);
})->with(['guest', 'account'])->with(['show', 'update', 'delete', 'increment', 'reset']);

test('guest routes require a valid unclaimed guest credential', function (string $credential) {
    $this->withHeader('X-Guest-Token', $credential)->getJson('/api/v1/guest/personal-wirds')->assertUnauthorized();
})->with(['', 'invalid', str_repeat('x', 64), ' '.str_repeat('x', 64).' ']);

test('Wird rejects invalid content and client controlled counters or ownership', function (string $field, mixed $value) {
    $actor = personalWirdActor('guest');
    $payload = ['text' => 'سبحان الله', 'target_count' => 33, $field => $value];

    $this->withHeaders($actor['headers'])->postJson($actor['base'], $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    $this->assertDatabaseCount('personal_wirds', 0);
})->with([
    'empty text' => ['text', '   '], 'oversized text' => ['text', str_repeat('x', 10001)],
    'zero target' => ['target_count', 0], 'large target' => ['target_count', 1000001],
    'fractional target' => ['target_count', 1.5],
    'counter injection' => ['current_count', 99], 'identity injection' => ['worship_identity_id', 123],
    'account injection' => ['user_id', 123],
]);

test('Wird rejects invalid pagination and empty patches', function () {
    $actor = personalWirdActor('account');
    $wird = PersonalWird::factory()->for($actor['identity'])->create();
    $this->withHeaders($actor['headers']);

    $this->getJson($actor['base'].'?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    $this->getJson($actor['base'].'?page=0')->assertUnprocessable()->assertJsonValidationErrors('page');
    $this->patchJson($actor['base'].'/'.$wird->id, [])->assertUnprocessable()->assertJsonValidationErrors('text');
});

test('invalid route IDs return 404 without internal errors', function (string $id) {
    $actor = personalWirdActor('guest');

    $this->withHeaders($actor['headers'])->getJson($actor['base'].'/'.$id)->assertNotFound();
})->with(['0', '-1', 'abc', '999999999999999999999999']);

test('registering with a guest credential transfers Wird atomically and revokes guest access', function () {
    $actor = personalWirdActor('guest');
    $wird = PersonalWird::factory()->for($actor['identity'])->create(['current_count' => 5]);
    $token = $actor['headers']['X-Guest-Token'];

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'أحمد', 'email' => 'claim@example.com', 'age' => 25,
        'password' => 'secure password', 'password_confirmation' => 'secure password',
        'device_name' => 'Android', 'guest_token' => $token,
    ]);

    $response->assertCreated();
    $user = User::sole();
    expect($user->personalWirds->sole()->is($wird))->toBeTrue();
    expect($user->worshipIdentities()->count())->toBe(1);
    expect($actor['identity']->fresh()->guest_token_hash)->toBeNull();
    $this->withHeader('X-Guest-Token', $token)->getJson($actor['base'])->assertUnauthorized();
    Auth::forgetGuards();
    $this->withToken($response->json('data.token'))->getJson('/api/v1/personal-wirds/'.$wird->id)
        ->assertOk()->assertJsonPath('data.current_count', 5);
});

test('invalid guest proof rolls registration back without creating accounts or tokens', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'أحمد', 'email' => 'invalid@example.com', 'age' => 25,
        'password' => 'secure password', 'password_confirmation' => 'secure password',
        'device_name' => 'Android', 'guest_token' => str_repeat('x', 64),
    ])->assertUnauthorized();

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('an authenticated account can claim a guest once and another account cannot replay its credential', function () {
    $guest = personalWirdActor('guest');
    $wird = PersonalWird::factory()->for($guest['identity'])->create();
    $user = User::factory()->create();
    $token = $user->createToken('mobile', ['mobile'])->plainTextToken;
    $payload = ['guest_token' => $guest['headers']['X-Guest-Token']];

    $this->withToken($token)->postJson('/api/v1/auth/guest-claim', $payload)->assertNoContent();

    expect($user->personalWirds->sole()->is($wird))->toBeTrue();
    Auth::forgetGuards();
    $other = User::factory()->create();
    $this->withToken($other->createToken('mobile', ['mobile'])->plainTextToken)
        ->postJson('/api/v1/auth/guest-claim', $payload)->assertUnauthorized();
    expect($guest['identity']->fresh()->user_id)->toBe($user->id);
});

test('a guest cannot claim identities without mobile account authentication', function () {
    $actor = personalWirdActor('guest');

    $this->withHeaders($actor['headers'])->postJson('/api/v1/auth/guest-claim', ['guest_token' => $actor['headers']['X-Guest-Token']])
        ->assertUnauthorized();
});

test('guest creation and access enforce their separate IP rate limits', function () {
    for ($request = 0; $request < 5; $request++) {
        $this->postJson('/api/v1/guest/identities')->assertCreated();
    }
    $this->postJson('/api/v1/guest/identities')->assertTooManyRequests();

    for ($request = 0; $request < 60; $request++) {
        $this->getJson('/api/v1/guest/personal-wirds')->assertUnauthorized();
    }
    $this->getJson('/api/v1/guest/personal-wirds')->assertTooManyRequests();
});

test('a full counter returns 409 on increment but can still be reset', function () {
    $actor = personalWirdActor('account');
    $wird = PersonalWird::factory()->for($actor['identity'])->create(['current_count' => PHP_INT_MAX]);
    $this->withHeaders($actor['headers']);

    $this->postJson($actor['base'].'/'.$wird->id.'/increment')->assertConflict();

    expect($wird->fresh()->current_count)->toBe(PHP_INT_MAX);
    $this->postJson($actor['base'].'/'.$wird->id.'/reset')->assertOk()->assertJsonPath('data.current_count', 0);
});

test('an existing account without an identity receives one when creating its first Wird', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('mobile', ['mobile'])->plainTextToken)
        ->postJson('/api/v1/personal-wirds', ['text' => 'الحمد لله', 'target_count' => 33])->assertCreated();

    expect($user->worshipIdentities()->count())->toBe(1);
    expect($user->personalWirds()->count())->toBe(1);
});

test('claiming rejects unsupported identity IDs and untrimmed guest credentials', function (array $payload, string $field) {
    $user = User::factory()->create();

    $this->withToken($user->createToken('mobile', ['mobile'])->plainTextToken)
        ->postJson('/api/v1/auth/guest-claim', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);

    $this->assertDatabaseCount('worship_identities', 0);
})->with([
    'ownership injection' => [['guest_token' => str_repeat('x', 64), 'user_id' => 99], 'user_id'],
    'credential whitespace' => [['guest_token' => ' '.str_repeat('x', 64).' '], 'guest_token'],
    'missing credential' => [[], 'guest_token'],
]);

test('guest claiming returns 429 after five failed proofs for the account', function () {
    $user = User::factory()->create();
    $this->withToken($user->createToken('mobile', ['mobile'])->plainTextToken);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/v1/auth/guest-claim', ['guest_token' => str_repeat('x', 64)])->assertUnauthorized();
    }
    $this->postJson('/api/v1/auth/guest-claim', ['guest_token' => str_repeat('x', 64)])->assertTooManyRequests();
});

<?php

use App\AccountStatus;
use App\Models\QuranBookmark;
use App\Models\QuranFavorite;
use App\Models\QuranReadingProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
});

/** Authenticate with an actual mobile token, preserving middleware coverage. */
function quranTrackingUser(): User
{
    $user = User::factory()->create();
    test()->withToken($user->createToken('mobile', ['mobile'])->plainTextToken);

    return $user;
}

/** Synthetic metadata only; automated tests never request real scripture. */
function fakeQuranTrackingMetadata(): void
{
    Http::fake(['api.alquran.cloud/v1/surah' => Http::response(['code' => 200, 'data' => array_map(fn (int $number): array => [
        'number' => $number, 'name' => 'اختبار', 'englishName' => 'Test', 'englishNameTranslation' => 'Test',
        'numberOfAyahs' => 7, 'revelationType' => 'Meccan',
    ], range(1, 114))])]);
}

test('bookmarks and favorites create once and expose only safe fields', function (string $kind) {
    $user = quranTrackingUser();
    fakeQuranTrackingMetadata();
    $input = ['surah_number' => 1, 'ayah_number' => 7];
    if ($kind === 'bookmarks') {
        $input['label'] = 'مراجعة';
    }

    $response = $this->postJson('/api/v1/quran/'.$kind, $input)->assertCreated()->assertHeader('Cache-Control', 'no-store, private');
    $id = $response->json('data.id');
    $this->postJson('/api/v1/quran/'.$kind, ['surah_number' => 1, 'ayah_number' => 7])
        ->assertOk()->assertJsonPath('data.id', $id);
    $expected = ['id' => $id, 'surah_number' => 1, 'ayah_number' => 7];
    if ($kind === 'bookmarks') {
        $expected['label'] = 'مراجعة';
    }
    $this->getJson('/api/v1/quran/'.$kind.'/'.$id)->assertOk()->assertExactJson(['data' => $expected]);
    $this->assertDatabaseCount('quran_'.$kind, 1);
    $this->assertDatabaseHas('quran_'.$kind, ['id' => $id, 'user_id' => $user->id, 'surah_number' => 1, 'ayah_number' => 7]);
    Http::assertSentCount(1);
})->with(['bookmarks', 'favorites']);

test('bookmark labels can be changed cleared and changed through duplicate saves', function () {
    $user = quranTrackingUser();
    $bookmark = QuranBookmark::factory()->for($user)->create(['label' => 'Old']);

    $this->patchJson('/api/v1/quran/bookmarks/'.$bookmark->id, ['label' => '  مراجعتي  '])
        ->assertOk()->assertJsonPath('data.label', 'مراجعتي');
    $this->assertDatabaseHas('quran_bookmarks', ['id' => $bookmark->id, 'label' => 'مراجعتي']);
    $this->patchJson('/api/v1/quran/bookmarks/'.$bookmark->id, ['label' => null])->assertOk()->assertJsonPath('data.label', null);
    Http::assertNothingSent();
    fakeQuranTrackingMetadata();
    $this->postJson('/api/v1/quran/bookmarks', ['surah_number' => 1, 'ayah_number' => 1, 'label' => 'New'])
        ->assertOk()->assertJsonPath('data.label', 'New');
    $this->assertDatabaseCount('quran_bookmarks', 1);
    $this->assertDatabaseHas('quran_bookmarks', ['id' => $bookmark->id, 'label' => 'New']);
    Http::assertSentCount(1);
});

test('tracking lists are bounded ordered and contain only the callers records', function (string $kind) {
    $user = quranTrackingUser();
    $model = $kind === 'bookmarks' ? QuranBookmark::class : QuranFavorite::class;
    $records = collect(range(1, 3))->map(fn (int $ayah) => $model::factory()->for($user)->create(['ayah_number' => $ayah]));
    $model::factory()->create();

    $this->getJson('/api/v1/quran/'.$kind.'?per_page=2')->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $records->last()->id)->assertJsonPath('meta.total', 3)->assertJsonPath('meta.last_page', 2);
    $this->getJson('/api/v1/quran/'.$kind.'?per_page=2&page=2')->assertOk()->assertJsonCount(1, 'data');
    Http::assertNothingSent();
})->with(['bookmarks', 'favorites']);

test('owned bookmarks and favorites can be deleted while other accounts are unchanged', function (string $kind) {
    $user = quranTrackingUser();
    $model = $kind === 'bookmarks' ? QuranBookmark::class : QuranFavorite::class;
    $owned = $model::factory()->for($user)->create();
    $other = $model::factory()->create();

    $this->deleteJson('/api/v1/quran/'.$kind.'/'.$owned->id)->assertNoContent();
    $this->assertModelMissing($owned);
    $this->assertModelExists($other);
    $this->getJson('/api/v1/quran/'.$kind.'/'.$owned->id)->assertNotFound();
    Http::assertNothingSent();
})->with(['bookmarks', 'favorites']);

test('foreign tracking IDs return 404 and cannot be read changed or deleted', function (string $kind, string $method) {
    quranTrackingUser();
    $model = $kind === 'bookmarks' ? QuranBookmark::class : QuranFavorite::class;
    $foreign = $model::factory()->create();

    $this->{$method}('/api/v1/quran/'.$kind.'/'.$foreign->id, $method === 'patchJson' ? ['label' => 'Attack'] : [])->assertNotFound();
    $this->assertModelExists($foreign);
    if ($kind === 'bookmarks') {
        expect($foreign->fresh()->label)->toBeNull();
    }
    Http::assertNothingSent();
})->with([
    'bookmark read' => ['bookmarks', 'getJson'], 'bookmark update' => ['bookmarks', 'patchJson'],
    'bookmark delete' => ['bookmarks', 'deleteJson'], 'favorite read' => ['favorites', 'getJson'], 'favorite delete' => ['favorites', 'deleteJson'],
]);

test('reading progress starts null and supports owned replacement and clearing', function () {
    $user = quranTrackingUser();
    $other = QuranReadingProgress::factory()->create();
    $this->freezeTime();

    $this->getJson('/api/v1/quran/reading-progress')->assertOk()->assertExactJson(['data' => null]);
    $this->assertDatabaseCount('quran_reading_progress', 1);
    Http::assertNothingSent();
    fakeQuranTrackingMetadata();
    $this->putJson('/api/v1/quran/reading-progress', ['surah_number' => 1, 'ayah_number' => 2])
        ->assertOk()->assertExactJson(['data' => ['surah_number' => 1, 'ayah_number' => 2, 'last_read_at' => now()->toIso8601String()]]);
    $this->travel(1)->minute();
    $this->putJson('/api/v1/quran/reading-progress', ['surah_number' => 2, 'ayah_number' => 1])->assertOk()
        ->assertJsonPath('data.last_read_at', now()->toIso8601String());
    $this->getJson('/api/v1/quran/reading-progress')->assertOk()->assertJsonPath('data.surah_number', 2);
    $this->assertDatabaseCount('quran_reading_progress', 2);
    $this->assertDatabaseHas('quran_reading_progress', ['user_id' => $user->id, 'surah_number' => 2, 'ayah_number' => 1]);
    $this->deleteJson('/api/v1/quran/reading-progress')->assertNoContent();
    $this->deleteJson('/api/v1/quran/reading-progress')->assertNoContent();
    $this->getJson('/api/v1/quran/reading-progress')->assertOk()->assertExactJson(['data' => null]);
    $this->assertDatabaseMissing('quran_reading_progress', ['user_id' => $user->id]);
    $this->assertModelExists($other);
    Http::assertSentCount(1);
});

test('all tracking endpoints require authentication and return 401', function (string $method, string $path) {
    $this->{$method}('/api/v1/quran/'.$path, [])->assertUnauthorized();
    Http::assertNothingSent();
})->with([
    ['getJson', 'bookmarks'], ['postJson', 'bookmarks'], ['getJson', 'bookmarks/1'], ['patchJson', 'bookmarks/1'], ['deleteJson', 'bookmarks/1'],
    ['getJson', 'favorites'], ['postJson', 'favorites'], ['getJson', 'favorites/1'], ['deleteJson', 'favorites/1'],
    ['getJson', 'reading-progress'], ['putJson', 'reading-progress'], ['deleteJson', 'reading-progress'],
]);

test('suspended accounts and tokens without mobile ability return 403', function (string $kind) {
    $user = User::factory()->create(['account_status' => $kind === 'suspended' ? AccountStatus::Suspended : AccountStatus::Active]);
    $this->withToken($user->createToken('test', $kind === 'suspended' ? ['mobile'] : ['read'])->plainTextToken);

    $this->postJson('/api/v1/quran/bookmarks', ['surah_number' => 1, 'ayah_number' => 1])->assertForbidden();
    $this->assertDatabaseCount('quran_bookmarks', 0);
    Http::assertNothingSent();
})->with(['suspended', 'wrong ability']);

test('browser sessions cannot substitute for mobile tracking tokens', function () {
    $this->actingAs(User::factory()->create(), 'web');

    $this->getJson('/api/v1/quran/bookmarks')->assertUnauthorized();
    Http::assertNothingSent();
});

test('invalid tracking fields return 422 without writes or provider requests', function (array $input, string $field) {
    quranTrackingUser();

    $this->postJson('/api/v1/quran/bookmarks', $input)->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('quran_bookmarks', 0);
    Http::assertNothingSent();
})->with([
    'missing reference' => [[], 'surah_number'],
    'surah lower bound' => [['surah_number' => 0, 'ayah_number' => 1], 'surah_number'],
    'surah upper bound' => [['surah_number' => 115, 'ayah_number' => 1], 'surah_number'],
    'surah array' => [['surah_number' => [1], 'ayah_number' => 1], 'surah_number'],
    'ayah upper bound' => [['surah_number' => 1, 'ayah_number' => 287], 'ayah_number'],
    'ayah lower bound' => [['surah_number' => 1, 'ayah_number' => 0], 'ayah_number'],
    'fractional ayah' => [['surah_number' => 1, 'ayah_number' => 1.5], 'ayah_number'],
    'label array' => [['surah_number' => 1, 'ayah_number' => 1, 'label' => ['x']], 'label'],
    'label too long' => [['surah_number' => 1, 'ayah_number' => 1, 'label' => str_repeat('x', 161)], 'label'],
    'label HTML' => [['surah_number' => 1, 'ayah_number' => 1, 'label' => '<script>x</script>'], 'label'],
    'label null byte' => [['surah_number' => 1, 'ayah_number' => 1, 'label' => "x\0x"], 'label'],
    'owner injection' => [['surah_number' => 1, 'ayah_number' => 1, 'user_id' => 2], 'user_id'],
]);

test('nonexistent ayahs return 422 with a useful error and no tracking writes', function (string $kind, string $method) {
    quranTrackingUser();
    fakeQuranTrackingMetadata();

    $this->{$method}('/api/v1/quran/'.$kind, ['surah_number' => 1, 'ayah_number' => 8])->assertUnprocessable()
        ->assertJsonValidationErrors('ayah_number')->assertJsonPath('errors.ayah_number.0', 'The ayah number does not exist in this surah.');
    $this->assertDatabaseCount('quran_bookmarks', 0);
    $this->assertDatabaseCount('quran_favorites', 0);
    $this->assertDatabaseCount('quran_reading_progress', 0);
    Http::assertSentCount(1);
})->with([['bookmarks', 'postJson'], ['favorites', 'postJson'], ['reading-progress', 'putJson']]);

test('bookmark updates reject changes to positions and empty patches', function (array $input, string $field) {
    $user = quranTrackingUser();
    $bookmark = QuranBookmark::factory()->for($user)->create(['label' => 'Original']);

    $this->patchJson('/api/v1/quran/bookmarks/'.$bookmark->id, $input)->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($bookmark->fresh()->label)->toBe('Original');
    Http::assertNothingSent();
})->with([[[], 'label'], [['label' => 'New', 'surah_number' => 2], 'surah_number'], [['label' => 'New', 'user_id' => 2], 'user_id']]);

test('favorites reject labels and progress rejects client timestamps', function (string $kind, string $method, string $field) {
    quranTrackingUser();

    $this->{$method}('/api/v1/quran/'.$kind, ['surah_number' => 1, 'ayah_number' => 1, $field => 'unsupported'])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    Http::assertNothingSent();
})->with([['favorites', 'postJson', 'label'], ['reading-progress', 'putJson', 'last_read_at'], ['reading-progress', 'putJson', 'user_id']]);

test('invalid tracking pagination and unexpected query fields return 422', function (string $query, string $field) {
    quranTrackingUser();

    $this->getJson('/api/v1/quran/bookmarks?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
    Http::assertNothingSent();
})->with([['page=0', 'page'], ['page=1000001', 'page'], ['per_page=101', 'per_page'], ['per_page=0', 'per_page'], ['page[]=1', 'page'], ['user_id=2', 'user_id']]);

test('malformed tracking record IDs return 404', function (string $id) {
    quranTrackingUser();

    $this->getJson('/api/v1/quran/bookmarks/'.$id)->assertNotFound();
    Http::assertNothingSent();
})->with(['0', '-1', 'abc', '1.5', '9999999999999999999']);

test('provider failure cannot create tracking records or overwrite progress', function (string $kind, string $method) {
    $user = quranTrackingUser();
    $progress = QuranReadingProgress::factory()->for($user)->create();
    Sleep::fake();
    Http::fake(['api.alquran.cloud/v1/surah' => Http::response([], 500)]);

    $this->{$method}('/api/v1/quran/'.$kind, ['surah_number' => 1, 'ayah_number' => 2])
        ->assertServiceUnavailable()->assertExactJson(['message' => 'Server error.']);
    $this->assertDatabaseCount('quran_bookmarks', 0);
    $this->assertDatabaseCount('quran_favorites', 0);
    expect($progress->fresh()->ayah_number)->toBe(1);
    Http::assertSentCount(2);
})->with([['bookmarks', 'postJson'], ['favorites', 'postJson'], ['reading-progress', 'putJson']]);

test('tracking writes have a separate limit and reads remain available', function () {
    quranTrackingUser();
    fakeQuranTrackingMetadata();

    for ($attempt = 0; $attempt < 30; $attempt++) {
        $this->putJson('/api/v1/quran/reading-progress', ['surah_number' => 1, 'ayah_number' => 1])->assertOk();
    }
    $this->postJson('/api/v1/quran/favorites', ['surah_number' => 1, 'ayah_number' => 1])->assertTooManyRequests()->assertHeader('Retry-After');
    $this->getJson('/api/v1/quran/reading-progress')->assertOk()->assertJsonPath('data.ayah_number', 1);
    $this->assertDatabaseCount('quran_favorites', 0);
    Http::assertSentCount(1);
});

test('tracking read limits apply per account with HTTP 429 after 120 requests', function () {
    quranTrackingUser();

    for ($attempt = 0; $attempt < 120; $attempt++) {
        $this->getJson('/api/v1/quran/bookmarks')->assertOk();
    }
    $this->getJson('/api/v1/quran/favorites')->assertTooManyRequests();
    Auth::forgetGuards();
    quranTrackingUser();
    $this->getJson('/api/v1/quran/bookmarks')->assertOk();
    Http::assertNothingSent();
});

test('saving the same verse as another account creates a separate owned record', function (string $kind) {
    $user = quranTrackingUser();
    $model = $kind === 'bookmarks' ? QuranBookmark::class : QuranFavorite::class;
    $foreign = $model::factory()->create();
    fakeQuranTrackingMetadata();

    $response = $this->postJson('/api/v1/quran/'.$kind, ['surah_number' => 1, 'ayah_number' => 1])->assertCreated();
    expect($response->json('data.id'))->not->toBe($foreign->id);
    $this->assertDatabaseHas('quran_'.$kind, ['id' => $response->json('data.id'), 'user_id' => $user->id]);
    $this->assertModelExists($foreign);
    $this->assertDatabaseCount('quran_'.$kind, 2);
    Http::assertSentCount(1);
})->with(['bookmarks', 'favorites']);

test('HEAD requests to tracking read routes do not require write fields', function () {
    quranTrackingUser();

    $this->json('HEAD', '/api/v1/quran/bookmarks')->assertOk();
    $this->json('HEAD', '/api/v1/quran/favorites')->assertOk();
    $this->json('HEAD', '/api/v1/quran/reading-progress')->assertOk();
    Http::assertNothingSent();
});

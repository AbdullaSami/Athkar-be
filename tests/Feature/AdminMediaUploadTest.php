<?php

use App\AccountStatus;
use App\Models\Category;
use App\Models\Dhikr;
use App\Models\User;
use Database\Seeders\ContentPermissionSeeder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToWriteFile;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

/** Create an editor using the existing permission definitions, independently of other test files. */
function mediaEditor(array $permissions = ['access-dashboard', 'manage-content']): User
{
    (new ContentPermissionSeeder)->run();
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user->fresh();
}

/** Read actual MIME bytes: Laravel's fake file otherwise guesses solely from its filename. */
function detectedMediaFile(string $name, string $bytes): File
{
    $file = UploadedFile::fake()->createWithContent($name, $bytes);

    return $file->mimeType((new finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()));
}

/** Generate a small PCM WAV fixture with real RIFF and format headers. */
function mediaWaveBytes(): string
{
    $samples = str_repeat("\0\0", 800);

    return 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16)
        .'data'.pack('V', strlen($samples)).$samples;
}

test('editors upload icons with generated paths and normalized PNG output', function (string $extension) {
    Storage::fake('public');
    $user = mediaEditor();
    $file = UploadedFile::fake()->image('client-private-name.'.$extension, 32, 48);
    $upload = detectedMediaFile('client-private-name.'.$extension, $file->get());

    $response = $this->actingAs($user, 'web')->post('/api/v1/admin/media/icons', ['file' => $upload], ['Accept' => 'application/json'])
        ->assertCreated()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('data.mime_type', 'image/png');
    $path = $response->json('data.path');
    expect($path)->toStartWith('icons/')->toEndWith('.png')->not->toContain('client-private-name');
    Storage::disk('public')->assertExists($path);
    $bytes = Storage::disk('public')->get($path);
    expect(substr($bytes, 0, 8))->toBe("\x89PNG\r\n\x1a\n");
    expect(getimagesizefromstring($bytes)[0])->toBe(32);
    expect(getimagesizefromstring($bytes)[1])->toBe(48);
    expect($response->json('data.size'))->toBe(strlen($bytes));
    expect($response->json('data.url'))->toBe(Storage::disk('public')->url($path));
    $this->assertDatabaseCount('categories', 0);
})->with(['png', 'jpg', 'webp']);

test('icon reencoding removes appended executable payloads', function () {
    Storage::fake('public');
    $image = UploadedFile::fake()->image('icon.png')->get();
    $file = detectedMediaFile('icon.png', $image.'<?php echo "private marker"; ?>');

    $response = $this->actingAs(mediaEditor(), 'web')->post('/api/v1/admin/media/icons', ['file' => $file], ['Accept' => 'application/json'])->assertCreated();
    $path = $response->json('data.path');
    Storage::disk('public')->assertExists($path);
    expect(Storage::disk('public')->get($path))->not->toContain('<?php')->not->toContain('private marker');
});

test('editors upload audio using detected bytes and generated names', function () {
    Storage::fake('public');
    $file = detectedMediaFile('original-sensitive-name.wav', mediaWaveBytes());

    $response = $this->actingAs(mediaEditor(), 'web')->post('/api/v1/admin/media/audio', ['file' => $file], ['Accept' => 'application/json'])->assertCreated();
    $path = $response->json('data.path');
    expect($path)->toStartWith('audio/')->toEndWith('.wav')->not->toContain('original-sensitive-name');
    Storage::disk('public')->assertExists($path);
    expect(Storage::disk('public')->get($path))->toBe(mediaWaveBytes());
    expect($response->json('data.size'))->toBe(strlen(mediaWaveBytes()));
});

test('repeated upload filenames cannot overwrite existing assets', function () {
    Storage::fake('public');
    $this->actingAs(mediaEditor(), 'web');
    $first = $this->post('/api/v1/admin/media/audio', ['file' => detectedMediaFile('same.wav', mediaWaveBytes())], ['Accept' => 'application/json'])->assertCreated();
    $second = $this->post('/api/v1/admin/media/audio', ['file' => detectedMediaFile('same.wav', mediaWaveBytes())], ['Accept' => 'application/json'])->assertCreated();

    expect($second->json('data.path'))->not->toBe($first->json('data.path'));
    Storage::disk('public')->assertExists([$first->json('data.path'), $second->json('data.path')]);
    expect(Storage::disk('public')->allFiles())->toHaveCount(2);
});

test('uploaded references work with content CRUD and cannot bypass live publication permission', function () {
    Storage::fake('public');
    $user = mediaEditor();
    $category = Category::factory()->create();
    $live = Dhikr::factory()->published()->create();
    $this->actingAs($user, 'web');
    $upload = $this->post('/api/v1/admin/media/icons', ['file' => UploadedFile::fake()->image('icon.png')], ['Accept' => 'application/json'])->assertCreated();
    $path = $upload->json('data.path');

    $this->patchJson('/api/v1/admin/categories/'.$category->id, ['icon_path' => $path])->assertOk()->assertJsonPath('data.icon_path', $path);
    $this->getJson('/api/v1/categories/'.$category->id)->assertOk()->assertJsonPath('data.icon_url', $upload->json('data.url'));
    $this->patchJson('/api/v1/admin/dhikrs/'.$live->id, ['icon_path' => $path])->assertForbidden();
    expect($live->fresh()->icon_path)->toBeNull();
    Storage::disk('public')->assertExists($path);
});

test('uploads require dashboard sessions and reject mobile tokens with HTTP 401', function (string $endpoint, bool $mobile) {
    Storage::fake('public');
    if ($mobile) {
        $user = mediaEditor();
        $this->withToken($user->createToken('mobile', ['mobile'])->plainTextToken);
    }
    $this->postJson('/api/v1/admin/media/'.$endpoint, [])->assertUnauthorized();
    expect(Storage::disk('public')->allFiles())->toBe([]);
})->with(['icons', 'audio'])->with([false, true]);

test('upload permission is denied to dashboard viewers and suspended editors', function (string $kind) {
    Storage::fake('public');
    $user = mediaEditor($kind === 'viewer' ? ['access-dashboard'] : ['access-dashboard', 'manage-content']);
    if ($kind === 'suspended') {
        $user->account_status = AccountStatus::Suspended;
        $user->save();
    }
    $this->actingAs($user, 'web')->postJson('/api/v1/admin/media/icons', [])->assertForbidden();
    expect(Storage::disk('public')->allFiles())->toBe([]);
})->with(['viewer', 'suspended']);

test('uploads enforce CSRF before storing files even for same origin administrators', function () {
    Storage::fake('public');
    $user = mediaEditor();
    $this->app->instance('env', 'local');
    $this->actingAs($user, 'web')->withHeader('Sec-Fetch-Site', 'same-origin')
        ->post('/api/v1/admin/media/icons', ['file' => UploadedFile::fake()->image('icon.png')], ['Accept' => 'application/json'])->assertStatus(419);
    expect(Storage::disk('public')->allFiles())->toBe([]);
    $this->withSession(['_token' => 'known-token'])->withHeader('X-CSRF-TOKEN', 'known-token')
        ->post('/api/v1/admin/media/icons', ['file' => UploadedFile::fake()->image('icon.png')], ['Accept' => 'application/json'])->assertCreated();
    expect(Storage::disk('public')->allFiles())->toHaveCount(1);
});

test('icon validation rejects unsupported formats dimensions sizes and client path injection', function (string $kind) {
    Storage::fake('public');
    $payload = match ($kind) {
        'missing' => [],
        'array' => ['file' => []],
        'string' => ['file' => 'not an upload'],
        'svg' => ['file' => detectedMediaFile('icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
        'gif' => ['file' => UploadedFile::fake()->image('icon.gif')],
        'script' => ['file' => detectedMediaFile('icon.png', '<?php echo "not an image"; ?>')],
        'html' => ['file' => detectedMediaFile('icon.png', '<html><script>alert(1)</script></html>')],
        'corrupt' => ['file' => UploadedFile::fake()->createWithContent('icon.png', 'not decodable')],
        'extension' => ['file' => detectedMediaFile('icon.php', UploadedFile::fake()->image('icon.png')->get())],
        'wide' => ['file' => UploadedFile::fake()->image('icon.png', 1025, 1)],
        'tall' => ['file' => UploadedFile::fake()->image('icon.png', 1, 1025)],
        'large' => ['file' => UploadedFile::fake()->image('icon.png')->size(2049)],
        'path' => ['file' => UploadedFile::fake()->image('icon.png'), 'path' => '../../private'],
    };
    $field = $kind === 'path' ? 'path' : 'file';

    $this->actingAs(mediaEditor(), 'web')->post('/api/v1/admin/media/icons', $payload, ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(Storage::disk('public')->allFiles())->toBe([]);
})->with(['missing', 'array', 'string', 'svg', 'gif', 'script', 'html', 'corrupt', 'extension', 'wide', 'tall', 'large', 'path']);

test('audio validation rejects forged MIME executable content invalid extensions and excess size', function (string $kind) {
    Storage::fake('public');
    $file = match ($kind) {
        'script' => detectedMediaFile('sound.wav', '<?php echo "not audio"; ?>'),
        'html' => detectedMediaFile('sound.mp3', '<html><script>alert(1)</script></html>'),
        'image' => detectedMediaFile('sound.wav', UploadedFile::fake()->image('icon.png')->get()),
        'extension' => detectedMediaFile('sound.php', mediaWaveBytes()),
        'large' => detectedMediaFile('sound.wav', mediaWaveBytes())->size(20481),
    };

    $this->actingAs(mediaEditor(), 'web')->post('/api/v1/admin/media/audio', ['file' => $file], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('file');
    expect(Storage::disk('public')->allFiles())->toBe([]);
})->with(['script', 'html', 'image', 'extension', 'large']);

test('audio extensions are determined from detected content instead of the original suffix', function () {
    Storage::fake('public');
    $file = detectedMediaFile('disguised.mp3', mediaWaveBytes());

    $response = $this->actingAs(mediaEditor(), 'web')->post('/api/v1/admin/media/audio', ['file' => $file], ['Accept' => 'application/json'])->assertCreated();
    expect($response->json('data.path'))->toEndWith('.wav');
    Storage::disk('public')->assertExists($response->json('data.path'));
});

test('storage write failure returns safe HTTP 503 without internal details', function (bool $throws) {
    $user = mediaEditor();
    $disk = Mockery::mock(FilesystemAdapter::class);
    $expectation = $disk->shouldReceive('put')->once();
    if ($throws) {
        $expectation->andThrow(UnableToWriteFile::atLocation('/private/path', 'private credential marker'));
    } else {
        $expectation->andReturn(false);
    }
    Storage::shouldReceive('disk')->with('public')->andReturn($disk);

    $this->actingAs($user, 'web')->post('/api/v1/admin/media/icons', ['file' => UploadedFile::fake()->image('icon.png')], ['Accept' => 'application/json'])
        ->assertStatus(503)->assertExactJson(['message' => 'Server error.']);
})->with([false, true]);

test('uploads have a separate ten request per administrator limit across media types', function () {
    Storage::fake('public');
    $this->actingAs(mediaEditor(), 'web');
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $this->postJson('/api/v1/admin/media/icons', [])->assertUnprocessable();
    }
    $this->postJson('/api/v1/admin/media/audio', [])->assertTooManyRequests()->assertHeader('Retry-After');
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('an image header without decodable pixels receives HTTP 422', function () {
    Storage::fake('public');
    $truncated = substr(UploadedFile::fake()->image('icon.png')->get(), 0, 33);
    $file = detectedMediaFile('icon.png', $truncated);

    $this->actingAs(mediaEditor(), 'web')->post('/api/v1/admin/media/icons', ['file' => $file], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonPath('errors.file.0', 'The icon could not be decoded.');
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('audio storage write failure receives safe HTTP 503', function () {
    $user = mediaEditor();
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('putFileAs')->once()->andReturn(false);
    Storage::shouldReceive('disk')->with('public')->andReturn($disk);

    $this->actingAs($user, 'web')->post('/api/v1/admin/media/audio', ['file' => detectedMediaFile('sound.wav', mediaWaveBytes())], ['Accept' => 'application/json'])
        ->assertStatus(503)->assertExactJson(['message' => 'Server error.']);
});

test('oversized HTTP request bodies receive HTTP 413 before upload storage', function () {
    Storage::fake('public');
    $this->actingAs(mediaEditor(), 'web')->withServerVariables(['CONTENT_LENGTH' => 1073741824])
        ->postJson('/api/v1/admin/media/audio', [])->assertStatus(413);
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

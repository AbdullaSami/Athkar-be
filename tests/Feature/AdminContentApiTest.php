<?php

use App\AccountStatus;
use App\Models\Category;
use App\Models\Dhikr;
use App\Models\Subcategory;
use App\Models\User;
use App\PublicationStatus;
use App\Services\AdminContentService;
use Database\Seeders\ContentPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

/** Grant only explicitly selected permissions without relying on another test file. */
function contentAdmin(array $permissions = ['access-dashboard', 'manage-content', 'publish-content']): User
{
    (new ContentPermissionSeeder)->run();
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user->fresh();
}

/** @return array<string, mixed> Synthetic fixture, never production religious text. */
function adminDhikrPayload(Subcategory $subcategory, array $overrides = []): array
{
    return array_replace([
        'subcategory_id' => $subcategory->id, 'title' => 'Test content', 'text' => "  نص للتجربة فقط\nسطر ثان  ",
        'description' => 'Test description', 'source' => 'Test source', 'virtue' => 'Test virtue',
        'repetition_target' => 3,
    ], $overrides);
}

test('admin content CRUD requires a dashboard session and refuses mobile credentials with HTTP 401', function (string $resource, bool $mobile) {
    if ($mobile) {
        $user = contentAdmin();
        $this->withToken($user->createToken('mobile', ['mobile'])->plainTextToken);
    }

    $this->getJson('/api/v1/admin/'.$resource)->assertUnauthorized();
    $this->postJson('/api/v1/admin/'.$resource, [])->assertUnauthorized();
})->with(['categories', 'subcategories', 'dhikrs'])->with([false, true]);

test('dashboard access alone cannot manage content with HTTP 403', function (string $resource) {
    $user = contentAdmin(['access-dashboard']);
    $this->actingAs($user, 'web')->getJson('/api/v1/admin/'.$resource)->assertForbidden();
    $this->postJson('/api/v1/admin/'.$resource, [])->assertForbidden();
})->with(['categories', 'subcategories', 'dhikrs']);

test('suspended administrators cannot manage content', function () {
    $user = contentAdmin();
    $user->account_status = AccountStatus::Suspended;
    $user->save();

    $this->actingAs($user, 'web')->getJson('/api/v1/admin/dhikrs')->assertForbidden();
});

test('category editors can create read update list and delete leaf categories', function () {
    $user = contentAdmin(['access-dashboard', 'manage-content']);
    $this->actingAs($user, 'web');
    $created = $this->postJson('/api/v1/admin/categories', ['title' => '  Test category  ', 'description' => null, 'sort_order' => 10])
        ->assertCreated()->assertJsonPath('data.title', 'Test category');
    $id = $created->json('data.id');
    $this->assertDatabaseHas('categories', ['id' => $id, 'title' => 'Test category', 'sort_order' => 10]);
    $this->getJson('/api/v1/admin/categories/'.$id)->assertOk()->assertJsonPath('data.id', $id);
    $this->patchJson('/api/v1/admin/categories/'.$id, ['description' => 'New description'])->assertOk()->assertJsonPath('data.description', 'New description');
    $this->getJson('/api/v1/admin/categories?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);
    $this->deleteJson('/api/v1/admin/categories/'.$id)->assertNoContent();
    $this->assertDatabaseMissing('categories', ['id' => $id]);
});

test('subcategory editors can create move and delete leaf subcategories', function () {
    $user = contentAdmin();
    $first = Category::factory()->create();
    $second = Category::factory()->create();
    $this->actingAs($user, 'web');
    $response = $this->postJson('/api/v1/admin/subcategories', ['category_id' => $first->id, 'title' => 'Test child'])->assertCreated();
    $id = $response->json('data.id');
    $this->getJson('/api/v1/admin/subcategories/'.$id)->assertOk()->assertJsonPath('data.category_id', $first->id);
    $this->patchJson('/api/v1/admin/subcategories/'.$id, ['category_id' => $second->id])->assertOk()->assertJsonPath('data.category_id', $second->id);
    $this->assertDatabaseHas('subcategories', ['id' => $id, 'category_id' => $second->id]);
    $this->deleteJson('/api/v1/admin/subcategories/'.$id)->assertNoContent();
    $this->assertDatabaseMissing('subcategories', ['id' => $id]);
});

test('editors create drafts by default and preserve Arabic content bytes', function () {
    $user = contentAdmin(['access-dashboard', 'manage-content']);
    $subcategory = Subcategory::factory()->create();
    $payload = adminDhikrPayload($subcategory, ['source' => "  Source\nline  "]);

    $response = $this->actingAs($user, 'web')->postJson('/api/v1/admin/dhikrs', $payload)->assertCreated()
        ->assertJsonPath('data.publication_status', 'draft')->assertJsonPath('data.text', $payload['text'])
        ->assertJsonPath('data.source', $payload['source']);
    $this->assertDatabaseHas('dhikrs', ['id' => $response->json('data.id'), 'text' => $payload['text'], 'publication_status' => 'draft']);
    $this->getJson('/api/v1/admin/dhikrs/'.$response->json('data.id'))->assertOk();
    $this->getJson('/api/v1/dhikrs/'.$response->json('data.id'))->assertNotFound();
});

test('publishers publish unpublish move edit and delete Dhikr', function () {
    $user = contentAdmin();
    $subcategory = Subcategory::factory()->create();
    $other = Subcategory::factory()->create();
    $this->actingAs($user, 'web');
    $created = $this->postJson('/api/v1/admin/dhikrs', adminDhikrPayload($subcategory, ['publication_status' => 'published']))->assertCreated();
    $id = $created->json('data.id');
    $this->getJson('/api/v1/dhikrs/'.$id)->assertOk();
    $this->patchJson('/api/v1/admin/dhikrs/'.$id, ['subcategory_id' => $other->id, 'virtue' => 'Edited virtue'])->assertOk()->assertJsonPath('data.virtue', 'Edited virtue');
    $this->assertDatabaseHas('dhikrs', ['id' => $id, 'subcategory_id' => $other->id, 'virtue' => 'Edited virtue']);
    $this->patchJson('/api/v1/admin/dhikrs/'.$id, ['publication_status' => 'draft'])->assertOk();
    $this->getJson('/api/v1/dhikrs/'.$id)->assertNotFound();
    $this->deleteJson('/api/v1/admin/dhikrs/'.$id)->assertNoContent();
    $this->assertDatabaseMissing('dhikrs', ['id' => $id]);
});

test('editors cannot publish create live content or modify live content with HTTP 403', function () {
    $user = contentAdmin(['access-dashboard', 'manage-content']);
    $draft = Dhikr::factory()->create();
    $published = Dhikr::factory()->published()->create();
    $this->actingAs($user, 'web');
    $this->postJson('/api/v1/admin/dhikrs', adminDhikrPayload($draft->subcategory, ['publication_status' => 'published']))->assertForbidden();
    $this->patchJson('/api/v1/admin/dhikrs/'.$draft->id, ['publication_status' => 'published'])->assertForbidden();
    $this->patchJson('/api/v1/admin/dhikrs/'.$published->id, ['title' => 'Unauthorized edit'])->assertForbidden();
    $this->deleteJson('/api/v1/admin/dhikrs/'.$published->id)->assertForbidden();
    expect($draft->fresh()->publication_status)->toBe(PublicationStatus::Draft);
    expect($published->fresh()->title)->not->toBe('Unauthorized edit');
    $this->assertDatabaseCount('dhikrs', 2);
});

test('editors can edit and remove drafts without publication permission', function () {
    $user = contentAdmin(['access-dashboard', 'manage-content']);
    $draft = Dhikr::factory()->create();
    $this->actingAs($user, 'web')->patchJson('/api/v1/admin/dhikrs/'.$draft->id, ['text' => 'Edited fixture'])->assertOk();
    expect($draft->fresh()->text)->toBe('Edited fixture');
    $this->deleteJson('/api/v1/admin/dhikrs/'.$draft->id)->assertNoContent();
});

test('admin listing supports fixed parent and publication filters with pagination', function () {
    $user = contentAdmin(['access-dashboard', 'manage-content']);
    $subcategory = Subcategory::factory()->create();
    $first = Dhikr::factory()->for($subcategory)->create(['sort_order' => 1]);
    Dhikr::factory()->for($subcategory)->create(['sort_order' => 2]);
    Dhikr::factory()->for($subcategory)->published()->create();
    Dhikr::factory()->create();

    $this->actingAs($user, 'web')->getJson('/api/v1/admin/dhikrs?subcategory_id='.$subcategory->id.'&publication_status=draft&per_page=1')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.id', $first->id);
    $this->getJson('/api/v1/admin/subcategories?category_id='.$subcategory->category_id)->assertOk()->assertJsonCount(1, 'data');
});

test('parent deletion returns safe HTTP 409 and preserves dependent records', function (string $kind) {
    $user = contentAdmin();
    $subcategory = Subcategory::factory()->create();
    if ($kind === 'subcategories') {
        Dhikr::factory()->for($subcategory)->create();
    }
    $id = $kind === 'categories' ? $subcategory->category_id : $subcategory->id;

    $this->actingAs($user, 'web')->deleteJson('/api/v1/admin/'.$kind.'/'.$id)->assertConflict()
        ->assertExactJson(['message' => 'Conflict']);
    $this->assertDatabaseHas($kind, ['id' => $id]);
    $this->assertModelExists($subcategory);
})->with(['categories', 'subcategories']);

test('admin writes require CSRF even for authenticated publishers on same origin', function () {
    $user = contentAdmin();
    $this->app->instance('env', 'local');
    $this->actingAs($user, 'web')->withHeader('Sec-Fetch-Site', 'same-origin')
        ->postJson('/api/v1/admin/categories', ['title' => 'Blocked'])->assertStatus(419);
    $this->assertDatabaseCount('categories', 0);
    $this->withSession(['_token' => 'known-token'])->withHeader('X-CSRF-TOKEN', 'known-token')
        ->postJson('/api/v1/admin/categories', ['title' => 'Allowed'])->assertCreated();
    $this->assertDatabaseHas('categories', ['title' => 'Allowed']);
});

test('invalid Dhikr data returns HTTP 422 without creating content', function (array $changes, string $field) {
    $user = contentAdmin();
    $subcategory = Subcategory::factory()->create();
    $payload = adminDhikrPayload($subcategory, $changes);

    $this->actingAs($user, 'web')->postJson('/api/v1/admin/dhikrs', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('dhikrs', 0);
})->with([
    'missing title' => [['title' => null], 'title'],
    'long title' => [['title' => str_repeat('x', 161)], 'title'],
    'blank text' => [['text' => '   '], 'text'],
    'large text' => [['text' => str_repeat('a', 10001)], 'text'],
    'missing description' => [['description' => null], 'description'],
    'missing source' => [['source' => null], 'source'],
    'missing virtue' => [['virtue' => null], 'virtue'],
    'missing parent' => [['subcategory_id' => 999999], 'subcategory_id'],
    'invalid status' => [['publication_status' => 'review'], 'publication_status'],
    'zero target' => [['repetition_target' => 0], 'repetition_target'],
    'large target' => [['repetition_target' => 1000001], 'repetition_target'],
    'negative order' => [['sort_order' => -1], 'sort_order'],
    'large order' => [['sort_order' => 1000001], 'sort_order'],
    'script markup' => [['text' => '<script>alert(1)</script>'], 'text'],
    'HTML source' => [['source' => '<a href="javascript:alert(1)">x</a>'], 'source'],
    'null byte' => [['text' => "text\0"], 'text'],
    'owner injection' => [['user_id' => 1], 'user_id'],
    'counter injection' => [['current_count' => 10], 'current_count'],
    'audio traversal' => [['audio_path' => 'audio/../private/test.mp3'], 'audio_path'],
    'absolute audio' => [['audio_path' => '/etc/passwd'], 'audio_path'],
    'remote audio' => [['audio_path' => 'https://attacker.example/test.mp3'], 'audio_path'],
    'executable icon' => [['icon_path' => 'icons/test.svg'], 'icon_path'],
    'encoded traversal' => [['icon_path' => 'icons/%2e%2e/test.png'], 'icon_path'],
]);

test('optional content values may be cleared and relative media references are accepted', function () {
    $user = contentAdmin();
    $subcategory = Subcategory::factory()->create();
    $response = $this->actingAs($user, 'web')->postJson('/api/v1/admin/dhikrs', adminDhikrPayload($subcategory, [
        'repetition_target' => null, 'explanation' => null, 'icon_path' => 'icons/test.png', 'audio_path' => 'audio/test.mp3',
    ]))->assertCreated()->assertJsonPath('data.repetition_target', null)->assertJsonPath('data.audio_path', 'audio/test.mp3');
    $this->patchJson('/api/v1/admin/dhikrs/'.$response->json('data.id'), ['audio_path' => null])->assertOk()->assertJsonPath('data.audio_url', null);
    $this->assertDatabaseHas('dhikrs', ['id' => $response->json('data.id'), 'audio_path' => null]);
});

test('empty patches unsupported fields and incomplete PUT requests receive HTTP 422', function () {
    $user = contentAdmin();
    $category = Category::factory()->create();
    $subcategory = Subcategory::factory()->for($category)->create();
    $this->actingAs($user, 'web')->patchJson('/api/v1/admin/categories/'.$category->id, [])->assertUnprocessable()->assertJsonValidationErrors('content');
    $this->patchJson('/api/v1/admin/categories/'.$category->id, ['roles' => ['admin']])->assertUnprocessable()->assertJsonValidationErrors('roles');
    $this->putJson('/api/v1/admin/categories/'.$category->id, ['description' => 'Only one field'])->assertUnprocessable()->assertJsonValidationErrors('title');
    $this->postJson('/api/v1/admin/subcategories', ['title' => 'Orphan', 'category_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('category_id');
    $this->getJson('/api/v1/admin/dhikrs?sort=title')->assertUnprocessable()->assertJsonValidationErrors('sort');
    $this->getJson('/api/v1/admin/categories?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    $this->getJson('/api/v1/admin/subcategories/'.$subcategory->id.'?include=dhikrs')->assertUnprocessable()->assertJsonValidationErrors('include');
});

test('admin route parameters reject malformed and oversized IDs with HTTP 404', function (string $path) {
    $user = contentAdmin();
    $this->actingAs($user, 'web')->getJson('/api/v1/admin/'.$path)->assertNotFound();
})->with(['categories/0', 'subcategories/-1', 'dhikrs/abc', 'dhikrs/999999999999999999999999', 'categories/999999']);

test('content policies enforce the complete editor and publisher permission matrix', function (bool $dashboard, bool $manage, bool $publish) {
    $permissions = array_filter([$dashboard ? 'access-dashboard' : null, $manage ? 'manage-content' : null, $publish ? 'publish-content' : null]);
    $user = contentAdmin(array_values($permissions));
    $draft = Dhikr::factory()->make();
    $live = Dhikr::factory()->published()->make();
    $editor = $dashboard && $manage;
    $publisher = $editor && $publish;

    foreach ([Category::class, Subcategory::class, Dhikr::class] as $model) {
        expect(Gate::forUser($user)->allows('viewAny', $model))->toBe($editor);
        expect(Gate::forUser($user)->allows('create', $model))->toBe($editor);
    }
    expect(Gate::forUser($user)->allows('view', $draft))->toBe($editor);
    expect(Gate::forUser($user)->allows('update', $draft))->toBe($editor);
    expect(Gate::forUser($user)->allows('delete', $draft))->toBe($editor);
    expect(Gate::forUser($user)->allows('update', $live))->toBe($publisher);
    expect(Gate::forUser($user)->allows('delete', $live))->toBe($publisher);
    expect(Gate::forUser($user)->allows('publish', $draft))->toBe($publisher);
})->with([false, true])->with([false, true])->with([false, true]);

test('admin content shares the authenticated administrator rate limit', function () {
    $user = contentAdmin();
    $this->actingAs($user, 'web');
    for ($attempt = 0; $attempt < 120; $attempt++) {
        $this->getJson('/api/v1/admin/categories')->assertOk();
    }
    $this->getJson('/api/v1/admin/dhikrs')->assertTooManyRequests()->assertHeader('Retry-After');
});

test('permission seeding is idempotent and does not grant privileges to accounts', function () {
    $user = User::factory()->create();
    $this->seed(ContentPermissionSeeder::class);
    $this->seed(ContentPermissionSeeder::class);

    $this->assertDatabaseCount('permissions', 5);
    expect($user->permissions()->count())->toBe(0);
    expect($user->roles()->count())->toBe(0);
});

test('legacy drafts with missing required information cannot be published', function (string $field) {
    $user = contentAdmin();
    $draft = Dhikr::factory()->create([$field => '']);

    $this->actingAs($user, 'web')->patchJson('/api/v1/admin/dhikrs/'.$draft->id, ['publication_status' => 'published'])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($draft->fresh()->publication_status)->toBe(PublicationStatus::Draft);
    $this->patchJson('/api/v1/admin/dhikrs/'.$draft->id, [$field => 'Repaired test fixture', 'publication_status' => 'published'])->assertOk();
    expect($draft->fresh()->publication_status)->toBe(PublicationStatus::Published);
})->with(['title', 'text', 'description', 'source', 'virtue']);

test('locked updates recheck publication authority after a draft is concurrently published', function () {
    $user = contentAdmin(['access-dashboard', 'manage-content']);
    $draft = Dhikr::factory()->create();
    Dhikr::query()->whereKey($draft->id)->update(['publication_status' => 'published']);
    $this->actingAs($user, 'web');

    expect(fn () => app(AdminContentService::class)->update($draft, ['title' => 'Forbidden change']))
        ->toThrow(AuthorizationException::class);
    expect($draft->fresh()->title)->not->toBe('Forbidden change');
});

test('legacy drafts containing executable markup cannot be published', function () {
    $user = contentAdmin();
    $draft = Dhikr::factory()->create(['source' => '<script>alert(1)</script>']);

    $this->actingAs($user, 'web')->patchJson('/api/v1/admin/dhikrs/'.$draft->id, ['publication_status' => 'published'])
        ->assertUnprocessable()->assertJsonValidationErrors('source');
    expect($draft->fresh()->publication_status)->toBe(PublicationStatus::Draft);
});

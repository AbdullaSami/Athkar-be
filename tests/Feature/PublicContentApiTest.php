<?php

use App\Models\Category;
use App\Models\Dhikr;
use App\Models\Subcategory;
use App\Models\User;
use App\PublicationStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config(['filesystems.disks.public.url' => 'https://media.example/storage']);
});

test('guests can browse category metadata in stable paginated order', function () {
    $last = Category::factory()->create(['sort_order' => 2]);
    $first = Category::factory()->create(['sort_order' => 1, 'icon_path' => 'icons/azkar.png']);
    $second = Category::factory()->create(['sort_order' => 1]);

    $response = $this->getJson('/api/v1/categories?per_page=2')->assertOk()
        ->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3)
        ->assertJsonPath('data.0.id', $first->id)->assertJsonPath('data.1.id', $second->id)
        ->assertJsonPath('data.0.icon_url', 'https://media.example/storage/icons/azkar.png');
    expect($response->json('data.0'))->toHaveKeys(['id', 'title', 'description', 'sort_order', 'icon_url'])
        ->not->toHaveKeys(['subcategories', 'icon_path']);
    expect($response->json('links.next'))->toContain('per_page=2');
    $this->getJson('/api/v1/categories?per_page=2&page=2')->assertOk()->assertJsonPath('data.0.id', $last->id);
});

test('category details do not embed unbounded children', function () {
    $category = Category::factory()->create(['title' => 'Test category', 'description' => 'Description']);
    Subcategory::factory()->for($category)->count(2)->create();

    $this->getJson('/api/v1/categories/'.$category->id)->assertOk()->assertExactJson(['data' => [
        'id' => $category->id, 'title' => 'Test category', 'description' => 'Description', 'icon_url' => null, 'sort_order' => 0,
    ]]);
});

test('subcategory lists are parent scoped ordered and count only published Dhikr', function () {
    $category = Category::factory()->create();
    $last = Subcategory::factory()->for($category)->create(['sort_order' => 2]);
    $first = Subcategory::factory()->for($category)->create(['sort_order' => 1]);
    $second = Subcategory::factory()->for($category)->create(['sort_order' => 1]);
    $other = Subcategory::factory()->create();
    Dhikr::factory()->for($first)->published()->count(2)->create();
    Dhikr::factory()->for($first)->count(3)->create();

    $this->getJson('/api/v1/categories/'.$category->id.'/subcategories?per_page=2')->assertOk()
        ->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3)
        ->assertJsonPath('data.0.id', $first->id)->assertJsonPath('data.1.id', $second->id)
        ->assertJsonPath('data.0.dhikrs_count', 2)->assertJsonMissing(['id' => $other->id]);
    $this->getJson('/api/v1/categories/'.$category->id.'/subcategories?per_page=2&page=2')
        ->assertOk()->assertJsonPath('data.0.id', $last->id);
});

test('subcategory details expose only published counts and safe metadata', function () {
    $subcategory = Subcategory::factory()->create();
    Dhikr::factory()->for($subcategory)->published()->create();
    Dhikr::factory()->for($subcategory)->create();

    $response = $this->getJson('/api/v1/subcategories/'.$subcategory->id)->assertOk()
        ->assertJsonPath('data.category_id', $subcategory->category_id)->assertJsonPath('data.dhikrs_count', 1);
    expect($response->json('data'))->not->toHaveKeys(['dhikrs', 'icon_path']);
});

test('published Dhikr lists exclude drafts and other subcategories with stable pagination', function () {
    $subcategory = Subcategory::factory()->create();
    $last = Dhikr::factory()->for($subcategory)->published()->create(['sort_order' => 2]);
    $first = Dhikr::factory()->for($subcategory)->published()->create(['sort_order' => 1]);
    $second = Dhikr::factory()->for($subcategory)->published()->create(['sort_order' => 1]);
    $draft = Dhikr::factory()->for($subcategory)->create(['title' => 'Private draft marker']);
    Dhikr::factory()->published()->create();

    $this->getJson('/api/v1/subcategories/'.$subcategory->id.'/dhikrs?per_page=2')->assertOk()
        ->assertJsonPath('meta.total', 3)->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $first->id)->assertJsonPath('data.1.id', $second->id)
        ->assertJsonMissing(['id' => $draft->id])->assertDontSee('Private draft marker');
    $this->getJson('/api/v1/subcategories/'.$subcategory->id.'/dhikrs?per_page=2&page=2')->assertOk()->assertJsonPath('data.0.id', $last->id);
});

test('published Dhikr details preserve Arabic text and provide public audio URLs', function () {
    $text = "  نص عربي للتجربة فقط\nسطر ثان  ";
    $dhikr = Dhikr::factory()->published()->create([
        'text' => $text, 'source' => 'Test source', 'virtue' => 'Test virtue', 'explanation' => 'Test explanation',
        'audio_path' => 'audio/test.mp3', 'repetition_target' => null,
    ]);

    $response = $this->getJson('/api/v1/dhikrs/'.$dhikr->id)->assertOk()
        ->assertJsonPath('data.text', $text)->assertJsonPath('data.source', 'Test source')
        ->assertJsonPath('data.virtue', 'Test virtue')->assertJsonPath('data.explanation', 'Test explanation')
        ->assertJsonPath('data.repetition_target', null)->assertJsonPath('data.audio_url', 'https://media.example/storage/audio/test.mp3')
        ->assertJsonPath('meta.previous_dhikr_id', null)->assertJsonPath('meta.next_dhikr_id', null);
    expect($response->json('data'))->not->toHaveKeys(['publication_status', 'audio_path', 'icon_path']);
});

test('Dhikr neighbors skip drafts use ID ties and never cross subcategories', function () {
    $subcategory = Subcategory::factory()->create();
    $first = Dhikr::factory()->for($subcategory)->published()->create(['sort_order' => 10]);
    Dhikr::factory()->for($subcategory)->create(['sort_order' => 10]);
    $middle = Dhikr::factory()->for($subcategory)->published()->create(['sort_order' => 10]);
    $last = Dhikr::factory()->for($subcategory)->published()->create(['sort_order' => 20]);
    Dhikr::factory()->published()->create(['sort_order' => 11]);

    $this->getJson('/api/v1/dhikrs/'.$middle->id)->assertOk()
        ->assertJsonPath('meta.previous_dhikr_id', $first->id)->assertJsonPath('meta.next_dhikr_id', $last->id);
    $this->getJson('/api/v1/dhikrs/'.$first->id)->assertOk()
        ->assertJsonPath('meta.previous_dhikr_id', null)->assertJsonPath('meta.next_dhikr_id', $middle->id);
    $this->getJson('/api/v1/dhikrs/'.$last->id)->assertOk()
        ->assertJsonPath('meta.previous_dhikr_id', $middle->id)->assertJsonPath('meta.next_dhikr_id', null);
});

test('draft Dhikr returns HTTP 404 even with an authenticated token', function (bool $authenticated) {
    $draft = Dhikr::factory()->create();
    if ($authenticated) {
        $this->withToken(User::factory()->create()->createToken('mobile', ['mobile'])->plainTextToken);
    }

    $this->getJson('/api/v1/dhikrs/'.$draft->id)->assertNotFound()->assertExactJson(['message' => 'Not found.']);
})->with(['guest' => false, 'account' => true]);

test('unpublishing immediately removes Dhikr from public details lists counts and neighbors', function () {
    $subcategory = Subcategory::factory()->create();
    $first = Dhikr::factory()->for($subcategory)->published()->create();
    $second = Dhikr::factory()->for($subcategory)->published()->create();
    $this->getJson('/api/v1/dhikrs/'.$second->id)->assertOk();
    $second->update(['publication_status' => PublicationStatus::Draft]);

    $this->getJson('/api/v1/dhikrs/'.$second->id)->assertNotFound();
    $this->getJson('/api/v1/subcategories/'.$subcategory->id.'/dhikrs')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/subcategories/'.$subcategory->id)->assertOk()->assertJsonPath('data.dhikrs_count', 1);
    $this->getJson('/api/v1/dhikrs/'.$first->id)->assertOk()->assertJsonPath('meta.next_dhikr_id', null);
});

test('empty content lists return bounded empty pages', function () {
    $category = Category::factory()->create();
    $subcategory = Subcategory::factory()->create();
    $this->getJson('/api/v1/categories/'.$category->id.'/subcategories')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
    $this->getJson('/api/v1/subcategories/'.$subcategory->id.'/dhikrs')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
});

test('missing public records and invalid route IDs receive HTTP 404', function (string $path) {
    $this->getJson('/api/v1/'.$path)->assertNotFound()->assertExactJson(['message' => 'Not found.']);
})->with([
    'categories/999999', 'categories/999999/subcategories', 'subcategories/999999',
    'subcategories/999999/dhikrs', 'dhikrs/999999', 'categories/0', 'categories/-1',
    'subcategories/abc', 'dhikrs/1e2', 'dhikrs/999999999999999999999999999',
]);

test('public pagination rejects invalid and unsupported input with HTTP 422', function (string $query, string $field) {
    $this->getJson('/api/v1/categories?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'zero page' => ['page=0', 'page'],
    'large page' => ['page=1000001', 'page'],
    'array page' => ['page[]=1', 'page'],
    'zero size' => ['per_page=0', 'per_page'],
    'large size' => ['per_page=101', 'per_page'],
    'fraction size' => ['per_page=1.5', 'per_page'],
    'array size' => ['per_page[]=1', 'per_page'],
    'unsupported sorting' => ['sort=id%20desc', 'sort'],
    'unsupported status' => ['publication_status=draft', 'publication_status'],
]);

test('nested lists also validate pagination and detail queries reject unexpected fields', function () {
    $subcategory = Subcategory::factory()->create();
    $dhikr = Dhikr::factory()->for($subcategory)->published()->create();
    $this->getJson('/api/v1/categories/'.$subcategory->category_id.'/subcategories?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    $this->getJson('/api/v1/subcategories/'.$subcategory->id.'/dhikrs?publication_status=draft')->assertUnprocessable()->assertJsonValidationErrors('publication_status');
    $this->getJson('/api/v1/categories/'.$subcategory->category_id.'?page=1')->assertUnprocessable()->assertJsonValidationErrors('page');
    $this->getJson('/api/v1/subcategories/'.$subcategory->id.'?include=dhikrs')->assertUnprocessable()->assertJsonValidationErrors('include');
    $this->getJson('/api/v1/dhikrs/'.$dhikr->id.'?publication_status=draft')->assertUnprocessable()->assertJsonValidationErrors('publication_status');
});

test('unsafe legacy media paths are omitted from public JSON', function (string $path) {
    $dhikr = Dhikr::factory()->published()->create(['icon_path' => $path, 'audio_path' => $path]);
    $this->getJson('/api/v1/dhikrs/'.$dhikr->id)->assertOk()->assertJsonPath('data.icon_url', null)->assertJsonPath('data.audio_url', null);
})->with(['../private/secret', '/etc/passwd', 'audio/../../secret', 'javascript:alert(1)', 'https://attacker.example/file', 'audio/%2e%2e/secret', 'audio/./test.mp3']);

test('public content never accepts writes', function () {
    $this->postJson('/api/v1/categories', ['title' => 'Injected'])->assertStatus(405);
    $this->assertDatabaseCount('categories', 0);
});

test('public content shares a sixty request guest limit across all read routes', function () {
    $category = Category::factory()->create();
    for ($request = 0; $request < 60; $request++) {
        $this->getJson('/api/v1/categories')->assertOk();
    }
    $this->getJson('/api/v1/categories/'.$category->id)->assertTooManyRequests()->assertHeader('Retry-After');
});

test('authenticated content limits are per user and guests behind the same IP remain separate', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $this->withToken($first->createToken('mobile', ['mobile'])->plainTextToken);
    for ($request = 0; $request < 120; $request++) {
        $this->getJson('/api/v1/categories')->assertOk();
    }
    $this->getJson('/api/v1/categories')->assertTooManyRequests();
    Auth::forgetGuards();
    $this->withToken($second->createToken('mobile', ['mobile'])->plainTextToken)->getJson('/api/v1/categories')->assertOk();
    Auth::forgetGuards();
    $this->withHeader('Authorization', '')->getJson('/api/v1/categories')->assertOk();
});

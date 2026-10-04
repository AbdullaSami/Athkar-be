<?php

use App\Models\AllahName;
use App\Models\Dhikr;
use App\Models\PersonalWird;
use App\Models\PrayerSetting;
use App\Models\QuranBookmark;
use App\Models\QuranFavorite;
use App\Models\QuranPreference;
use App\Models\QuranReadingProgress;
use App\Models\User;
use App\Models\WorshipIdentity;
use App\PublicationStatus;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);

test('content is navigable through its category and subcategory', function () {
    $dhikr = Dhikr::factory()->create();

    $category = $dhikr->subcategory->category;

    expect($category->subcategories->sole()->dhikrs->sole()->is($dhikr))->toBeTrue();
});

test('public content queries exclude drafts', function () {
    Dhikr::factory()->create();
    $published = Dhikr::factory()->published()->create();

    $results = Dhikr::published()->get();

    expect($results->modelKeys())->toBe([$published->id]);
    expect($results->sole()->publication_status)->toBe(PublicationStatus::Published);
});

test('content hierarchy cannot be deleted while dependent content exists', function (string $parent) {
    $dhikr = Dhikr::factory()->create();
    $model = $parent === 'category' ? $dhikr->subcategory->category : $dhikr->subcategory;

    expect(fn () => $model->delete())->toThrow(QueryException::class);

    $this->assertModelExists($model);
    $this->assertModelExists($dhikr);
})->with(['category', 'subcategory']);

test('a guest identity can be claimed without copying or losing its Wird', function () {
    $user = User::factory()->create();
    $wird = PersonalWird::factory()->create(['target_count' => 3, 'current_count' => 5]);
    $identity = $wird->worshipIdentity;

    DB::transaction(function () use ($user, $identity): void {
        $identity->user()->associate($user);
        $identity->guest_token_hash = null;
        $identity->save();
    });

    expect($user->personalWirds->sole()->is($wird))->toBeTrue();
    expect($wird->fresh()->current_count)->toBe(5);
    expect($identity->fresh()->guest_token_hash)->toBeNull();
});

test('Wird relationships isolate registered accounts and unclaimed guests', function () {
    $user = User::factory()->create();
    $identity = WorshipIdentity::factory()->registered($user)->create();
    $owned = PersonalWird::factory()->for($identity)->create();
    PersonalWird::factory()->for(WorshipIdentity::factory()->registered())->create();
    PersonalWird::factory()->create();

    $results = $user->personalWirds()->get();

    expect($results->modelKeys())->toBe([$owned->id]);
});

test('ownership and guest credentials cannot be mass assigned or exposed', function () {
    $identity = WorshipIdentity::factory()->create();
    $wird = PersonalWird::factory()->for($identity)->create();

    expect(fn () => $identity->fill(['user_id' => 123, 'guest_token_hash' => 'attacker']))
        ->toThrow(MassAssignmentException::class);
    $wird->fill(['worship_identity_id' => 123]);

    expect($identity->user_id)->toBeNull();
    expect($identity->guest_token_hash)->not->toBe('attacker');
    expect($identity->toArray())->not->toHaveKey('guest_token_hash');
    expect($wird->worship_identity_id)->toBe($identity->id);
});

test('an account has only one current settings or progress record', function (string $model) {
    $user = User::factory()->create();
    $record = $model::factory()->for($user)->create();

    expect(fn () => $model::factory()->for($user)->create())->toThrow(QueryException::class);

    $this->assertModelExists($record);
})->with([PrayerSetting::class, QuranReadingProgress::class, QuranPreference::class]);

test('Quran marks are unique per account while other accounts may mark the same verse', function (string $model) {
    $user = User::factory()->create();
    $record = $model::factory()->for($user)->create();
    $otherRecord = $model::factory()->create();

    expect(fn () => $model::factory()->for($user)->create())->toThrow(QueryException::class);

    $this->assertModelExists($record);
    $this->assertModelExists($otherRecord);
})->with([QuranBookmark::class, QuranFavorite::class]);

test('deleting an account removes its private worship data and preserves guest data', function () {
    $user = User::factory()->create();
    $identity = WorshipIdentity::factory()->registered($user)->create();
    $wird = PersonalWird::factory()->for($identity)->create();
    $guestWird = PersonalWird::factory()->create();
    $privateRecords = collect([PrayerSetting::class, QuranBookmark::class, QuranFavorite::class, QuranReadingProgress::class, QuranPreference::class])
        ->map(fn (string $model) => $model::factory()->for($user)->create());

    $user->delete();

    $this->assertModelMissing($identity);
    $this->assertModelMissing($wird);
    $privateRecords->each(fn ($record) => $this->assertModelMissing($record));
    $this->assertModelExists($guestWird);
});

test('Allah name translations are unique by number and language', function () {
    $arabic = AllahName::factory()->create(['number' => 1, 'language' => 'ar']);
    $english = AllahName::factory()->create(['number' => 1, 'language' => 'en']);

    expect(fn () => AllahName::factory()->create(['number' => 1, 'language' => 'ar']))->toThrow(QueryException::class);

    $this->assertModelExists($arabic);
    $this->assertModelExists($english);
});

test('private records cannot reference a nonexistent owner', function () {
    $wird = PersonalWird::factory()->make();
    $wird->worship_identity_id = 999999;

    expect(fn () => $wird->save())->toThrow(QueryException::class);

    $this->assertDatabaseCount('personal_wirds', 0);
});

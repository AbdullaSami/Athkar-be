<?php

namespace App\Services;

use App\Models\QuranBookmark;
use App\Models\QuranFavorite;
use App\Models\QuranPreference;
use App\Models\QuranReadingProgress;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Scope all Quran tracking to its owner and serialize first-time writes. */
class QuranTrackingService
{
    /** Reuse the validated, cached Quran metadata for reference checks. */
    public function __construct(private QuranService $quran) {}

    /** Return a stable, bounded page containing only this account's records. */
    public function list(User $user, bool $favorite, int $perPage): LengthAwarePaginator
    {
        return $this->records($user, $favorite)->orderByDesc('id')->paginate($perPage);
    }

    /** Resolve an ID within the account's relationship; foreign IDs return HTTP 404. */
    public function find(User $user, string $id, bool $favorite): QuranBookmark|QuranFavorite
    {
        return $this->records($user, $favorite)->whereKey($id)->firstOrFail();
    }

    /** @param array<string, mixed> $input Validated reference and optional bookmark label; duplicates reuse the same row. */
    public function save(User $user, array $input, bool $favorite): QuranBookmark|QuranFavorite
    {
        $this->validateReference($input);

        return DB::transaction(function () use ($user, $input, $favorite): QuranBookmark|QuranFavorite {
            $owner = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $record = $this->records($owner, $favorite)->firstOrNew([
                'surah_number' => (int) $input['surah_number'], 'ayah_number' => (int) $input['ayah_number'],
            ]);
            if (! $favorite && array_key_exists('label', $input)) {
                $record->label = $input['label'];
            }
            $record->save();

            return $record->refresh();
        }, 3);
    }

    /** @param array{label: ?string} $input Update only the label of an owned bookmark. */
    public function updateBookmark(User $user, string $id, array $input): QuranBookmark
    {
        return DB::transaction(function () use ($user, $id, $input): QuranBookmark {
            $bookmark = $user->quranBookmarks()->whereKey($id)->lockForUpdate()->firstOrFail();
            $bookmark->update($input);

            return $bookmark->refresh();
        }, 3);
    }

    /** Delete an owned bookmark or favorite without disclosing another account's records. */
    public function delete(User $user, string $id, bool $favorite): void
    {
        $this->find($user, $id, $favorite)->delete();
    }

    /** Return this account's last reading position, or null before the first update. */
    public function progress(User $user): ?QuranReadingProgress
    {
        return $user->quranReadingProgress()->first();
    }

    /** @param array<string, mixed> $input Validated position; the server supplies the read timestamp. */
    public function saveProgress(User $user, array $input): QuranReadingProgress
    {
        $this->validateReference($input);

        return DB::transaction(function () use ($user, $input): QuranReadingProgress {
            $owner = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $progress = $owner->quranReadingProgress()->first() ?? $owner->quranReadingProgress()->make();
            $progress->fill(['surah_number' => (int) $input['surah_number'], 'ayah_number' => (int) $input['ayah_number'], 'last_read_at' => now()])->save();

            return $progress->refresh();
        }, 3);
    }

    /** Clear only this account's reading position; repeated deletion is harmless. */
    public function deleteProgress(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $owner = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $owner->quranReadingProgress()->delete();
        }, 3);
    }

    /** Return owned preferences or unsaved defaults without writes or provider requests. */
    public function preferences(User $user): QuranPreference
    {
        return $user->quranPreference()->first() ?? new QuranPreference(['translation_edition' => null, 'reciter_identifier' => null]);
    }

    /** @param array<string, ?string> $input Validated partial preferences; null clears a selection. */
    public function savePreferences(User $user, array $input): QuranPreference
    {
        return DB::transaction(function () use ($user, $input): QuranPreference {
            $owner = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $preferences = $owner->quranPreference()->first() ?? $owner->quranPreference()->make();
            $preferences->fill($input)->save();

            return $preferences->refresh();
        }, 3);
    }

    /** Return a fixed application relationship, never a caller-selected model or query field. */
    private function records(User $user, bool $favorite): HasMany
    {
        return $favorite ? $user->quranFavorites() : $user->quranBookmarks();
    }

    /** @param array<string, mixed> $input Check the specific surah's ayah count before any database write. */
    private function validateReference(array $input): void
    {
        $surah = $this->quran->surah((int) $input['surah_number']);
        if ((int) $input['ayah_number'] > $surah['ayah_count']) {
            throw ValidationException::withMessages(['ayah_number' => 'The ayah number does not exist in this surah.']);
        }
    }
}

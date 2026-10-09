<?php

namespace App\Services;

use App\Models\PrayerSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Read and persist calculation preferences scoped to the authenticated account. */
class PrayerSettingService
{
    /** Return stored preferences or unsaved defaults without creating a row on GET. */
    public function show(User $user): PrayerSetting
    {
        return $user->prayerSetting()->first() ?? new PrayerSetting([
            'calculation_method' => null, 'school' => 0,
            'high_latitude_method' => null, 'timezone' => null,
        ]);
    }

    /** @param array<string, mixed> $input Validated partial preferences; ownership is never client-selected. */
    public function update(User $user, array $input): PrayerSetting
    {
        return DB::transaction(function () use ($user, $input): PrayerSetting {
            // Lock the parent even when its settings row has not been created yet.
            $owner = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $settings = $owner->prayerSetting()->first() ?? $owner->prayerSetting()->make();
            $settings->fill($input)->save();

            return $settings->refresh();
        }, 3);
    }
}

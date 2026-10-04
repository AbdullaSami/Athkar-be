<?php

namespace Database\Factories;

use App\Models\PrayerSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PrayerSetting> */
class PrayerSettingFactory extends Factory
{
    /** @return array<string, mixed> Synthetic test data, never production religious content. */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'calculation_method' => null,
            'school' => 0,
            'high_latitude_method' => null,
            'timezone' => null,
            'notifications_enabled' => false,
        ];
    }
}

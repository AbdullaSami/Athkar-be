<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Return calculation preferences without internal IDs or account information. */
class PrayerSettingResource extends JsonResource
{
    /** @return array<string, int|string|null> Null means use the location/provider default. */
    public function toArray(Request $request): array
    {
        return [
            'calculation_method' => $this->calculation_method,
            'school' => $this->school,
            'high_latitude_method' => $this->high_latitude_method,
            'timezone' => $this->timezone,
        ];
    }
}

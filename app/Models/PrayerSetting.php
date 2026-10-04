<?php

namespace App\Models;

use Database\Factories\PrayerSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stores application-owned PrayerSetting data; input must be validated by callers. */
#[Fillable(['calculation_method', 'school', 'high_latitude_method', 'timezone', 'notifications_enabled'])]
class PrayerSetting extends Model
{
    /** @use HasFactory<PrayerSettingFactory> */
    use HasFactory;

    /** Return the related User records. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> Attribute types returned by this model. */
    protected function casts(): array
    {
        return [
            'calculation_method' => 'integer',
            'school' => 'integer',
            'high_latitude_method' => 'integer',
            'notifications_enabled' => 'boolean',
        ];
    }
}

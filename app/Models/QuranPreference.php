<?php

namespace App\Models;

use Database\Factories\QuranPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stores application-owned QuranPreference data; input must be validated by callers. */
#[Fillable(['translation_edition', 'reciter_identifier'])]
class QuranPreference extends Model
{
    /** @use HasFactory<QuranPreferenceFactory> */
    use HasFactory;

    /** Return the related User records. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

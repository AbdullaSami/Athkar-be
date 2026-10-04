<?php

namespace App\Models;

use Database\Factories\QuranFavoriteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stores application-owned QuranFavorite data; input must be validated by callers. */
#[Fillable(['surah_number', 'ayah_number'])]
class QuranFavorite extends Model
{
    /** @use HasFactory<QuranFavoriteFactory> */
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
            'surah_number' => 'integer',
            'ayah_number' => 'integer',
        ];
    }
}

<?php

namespace App\Models;

use Database\Factories\PersonalWirdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stores application-owned PersonalWird data; input must be validated by callers. */
#[Fillable(['text', 'target_count', 'current_count'])]
class PersonalWird extends Model
{
    /** @use HasFactory<PersonalWirdFactory> */
    use HasFactory;

    /** Return the related WorshipIdentity records. */
    public function worshipIdentity(): BelongsTo
    {
        return $this->belongsTo(WorshipIdentity::class);
    }

    /** @return array<string, string> Attribute types returned by this model. */
    protected function casts(): array
    {
        return [
            'target_count' => 'integer',
            'current_count' => 'integer',
        ];
    }
}

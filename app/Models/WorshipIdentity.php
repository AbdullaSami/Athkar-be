<?php

namespace App\Models;

use Database\Factories\WorshipIdentityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Stores application-owned WorshipIdentity data; input must be validated by callers. */
#[Fillable([])]
#[Hidden(['guest_token_hash'])]
class WorshipIdentity extends Model
{
    /** @use HasFactory<WorshipIdentityFactory> */
    use HasFactory;

    /** Return the related User records. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Return the related PersonalWird records. */
    public function personalWirds(): HasMany
    {
        return $this->hasMany(PersonalWird::class);
    }
}

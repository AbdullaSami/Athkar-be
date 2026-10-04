<?php

namespace App\Models;

use Database\Factories\SubcategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Stores application-owned Subcategory data; input must be validated by callers. */
#[Fillable(['title', 'description', 'icon_path', 'sort_order'])]
class Subcategory extends Model
{
    /** @use HasFactory<SubcategoryFactory> */
    use HasFactory;

    /** Return the related Category records. */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Return the related Dhikr records. */
    public function dhikrs(): HasMany
    {
        return $this->hasMany(Dhikr::class);
    }

    /** @return array<string, string> Attribute types returned by this model. */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}

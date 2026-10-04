<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Stores application-owned Category data; input must be validated by callers. */
#[Fillable(['title', 'description', 'icon_path', 'sort_order'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /** Return the related Subcategory records. */
    public function subcategories(): HasMany
    {
        return $this->hasMany(Subcategory::class);
    }

    /** @return array<string, string> Attribute types returned by this model. */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}

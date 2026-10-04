<?php

namespace App\Models;

use App\PublicationStatus;
use Database\Factories\DhikrFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stores application-owned Dhikr data; input must be validated by callers. */
#[Fillable(['title', 'text', 'description', 'source', 'virtue', 'explanation', 'repetition_target', 'icon_path', 'audio_path', 'publication_status', 'sort_order'])]
class Dhikr extends Model
{
    /** @use HasFactory<DhikrFactory> */
    use HasFactory;

    /** Return the related Subcategory records. */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    /** @return array<string, string> Attribute types returned by this model. */
    protected function casts(): array
    {
        return [
            'publication_status' => PublicationStatus::class,
            'repetition_target' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** Restrict public content queries to published records. */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('publication_status', PublicationStatus::Published);
    }
}

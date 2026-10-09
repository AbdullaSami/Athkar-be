<?php

namespace App\Http\Resources;

use App\Models\Dhikr;
use App\Models\Subcategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Reuse display resources and add editor-only media references and publication metadata. */
class AdminContentResource extends JsonResource
{
    /** @return array<string, mixed> Bound metadata only; no user accounts or private storage fields. */
    public function toArray(Request $request): array
    {
        $resource = match (true) {
            $this->resource instanceof Dhikr => new DhikrResource($this->resource),
            $this->resource instanceof Subcategory => new SubcategoryResource($this->resource),
            default => new CategoryResource($this->resource),
        };
        $data = [...$resource->resolve($request), 'icon_path' => $this->icon_path,
            'created_at' => $this->created_at?->toIso8601String(), 'updated_at' => $this->updated_at?->toIso8601String()];
        if ($this->resource instanceof Dhikr) {
            $data['audio_path'] = $this->audio_path;
            $data['publication_status'] = $this->publication_status->value;
        }

        return $data;
    }
}

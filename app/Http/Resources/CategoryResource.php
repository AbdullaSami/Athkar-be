<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** Public category metadata without an unbounded nested collection. */
class CategoryResource extends PublicMediaResource
{
    /** @return array<string, mixed> Category metadata and a public icon URL. */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'description' => $this->description,
            'icon_url' => $this->mediaUrl($this->icon_path), 'sort_order' => $this->sort_order];
    }
}

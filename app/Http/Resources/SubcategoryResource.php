<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** Public subcategory metadata; draft counts are never returned. */
class SubcategoryResource extends PublicMediaResource
{
    /** @return array<string, mixed> Parent ID, display fields, and the published-only count. */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'category_id' => $this->category_id, 'title' => $this->title,
            'description' => $this->description, 'icon_url' => $this->mediaUrl($this->icon_path),
            'sort_order' => $this->sort_order, 'dhikrs_count' => $this->whenCounted('dhikrs')];
    }
}

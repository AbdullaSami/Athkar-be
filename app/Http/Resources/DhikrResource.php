<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** Published content as JSON; Arabic text is preserved without generating HTML. */
class DhikrResource extends PublicMediaResource
{
    /** @return array<string, mixed> Source, virtue, optional explanation, target and public media. */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'subcategory_id' => $this->subcategory_id, 'title' => $this->title,
            'text' => $this->text, 'description' => $this->description, 'source' => $this->source,
            'virtue' => $this->virtue, 'explanation' => $this->explanation,
            'repetition_target' => $this->repetition_target, 'sort_order' => $this->sort_order,
            'icon_url' => $this->mediaUrl($this->icon_path), 'audio_url' => $this->mediaUrl($this->audio_path)];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\QuranBookmark;
use App\Models\QuranReadingProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Return application-owned tracking without user IDs or provider data. */
class QuranTrackingResource extends JsonResource
{
    /** @return array<string, mixed> Expose only fields relevant to this tracking resource. */
    public function toArray(Request $request): array
    {
        $data = ['surah_number' => $this->surah_number, 'ayah_number' => $this->ayah_number];
        if ($this->resource instanceof QuranReadingProgress) {
            return [...$data, 'last_read_at' => $this->last_read_at->toIso8601String()];
        }
        $data = ['id' => $this->id, ...$data];
        if ($this->resource instanceof QuranBookmark) {
            $data['label'] = $this->label;
        }

        return $data;
    }
}

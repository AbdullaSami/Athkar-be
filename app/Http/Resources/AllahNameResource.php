<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Expose Arabic names and translated meanings without internal database identifiers. */
class AllahNameResource extends JsonResource
{
    /** @return array<string, int|string> Preserve Arabic text and diacritics. */
    public function toArray(Request $request): array
    {
        return ['number' => $this->number, 'arabic_name' => $this->arabic_name,
            'transliteration' => $this->transliteration, 'meaning' => $this->meaning, 'language' => $this->language];
    }
}

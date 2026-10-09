<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Expose supported Quran selections without owner IDs or internal database fields. */
class QuranPreferenceResource extends JsonResource
{
    /** @return array{translation_edition: ?string, reciter_identifier: ?string} Null means no saved selection. */
    public function toArray(Request $request): array
    {
        return ['translation_edition' => $this->translation_edition, 'reciter_identifier' => $this->reciter_identifier];
    }
}

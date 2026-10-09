<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Return owned Wird content without identity IDs or credentials. */
class PersonalWirdResource extends JsonResource
{
    /** @return array<string, mixed> Preserve Arabic text and expose the current target and count. */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'text' => $this->text, 'target_count' => $this->target_count,
            'current_count' => $this->current_count, 'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String()];
    }
}

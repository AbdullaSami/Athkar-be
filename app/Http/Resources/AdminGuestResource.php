<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Identify an anonymous app identity without exposing its credential or private content. */
class AdminGuestResource extends JsonResource
{
    /** @return array{identity_id: string, created_at: ?string} Guest overview representation. */
    public function toArray(Request $request): array
    {
        return ['identity_id' => $this->public_id, 'created_at' => $this->created_at?->toIso8601String()];
    }
}

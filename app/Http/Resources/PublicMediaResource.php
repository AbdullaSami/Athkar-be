<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** Shared public-media mapping; never expose absolute paths or executable URI schemes. */
abstract class PublicMediaResource extends JsonResource
{
    /** Convert a relative public-disk path to a URL; unsupported legacy paths become null. */
    protected function mediaUrl(?string $path): ?string
    {
        if ($path === null || preg_match('~^[A-Za-z0-9_-][A-Za-z0-9_./-]*$~D', $path) !== 1
            || in_array('..', explode('/', $path), true) || in_array('.', explode('/', $path), true)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}

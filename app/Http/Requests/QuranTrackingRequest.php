<?php

namespace App\Http\Requests;

use App\Http\Requests\Auth\StrictAuthRequest;
use App\Rules\PlainContentText;

/** Validate owned Quran references, bookmark labels and bounded list pagination. */
class QuranTrackingRequest extends StrictAuthRequest
{
    /** Only authenticated accounts may operate on their own tracking records. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> Record IDs and ownership are never accepted from input. */
    public function rules(): array
    {
        $action = $this->route()->getActionMethod();
        $read = $this->isMethod('GET') || $this->isMethod('HEAD');
        if ($read && $action === 'index') {
            return ['page' => ['sometimes', 'required', 'integer', 'between:1,1000000'],
                'per_page' => ['sometimes', 'required', 'integer', 'between:1,100']];
        }
        if ($read || $this->isMethod('DELETE')) {
            return [];
        }
        $label = ['label' => ['sometimes', 'nullable', 'string', 'max:160', new PlainContentText]];
        if ($this->routeIs('api.v1.quran.bookmarks.update')) {
            $label['label'][0] = 'present';

            return $label;
        }
        $rules = ['surah_number' => ['required', 'integer', 'between:1,114'],
            'ayah_number' => ['required', 'integer', 'between:1,286']];

        return $this->routeIs('api.v1.quran.bookmarks.store') ? [...$rules, ...$label] : $rules;
    }
}

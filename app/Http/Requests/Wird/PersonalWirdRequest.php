<?php

namespace App\Http\Requests\Wird;

use App\Http\Requests\Auth\StrictAuthRequest;
use App\Models\User;
use App\Models\WorshipIdentity;
use Illuminate\Validation\Validator;

/** Validate each Wird action and share the existing strict input boundary. */
class PersonalWirdRequest extends StrictAuthRequest
{
    /** Resolve the authenticated account or middleware-authenticated guest identity. */
    public function owner(): User|WorshipIdentity
    {
        return $this->attributes->get('worship_identity') ?? $this->user();
    }

    /** @return array<string, array<string>> Only content, targets and bounded pagination are client controlled. */
    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'index' => ['page' => ['sometimes', 'integer', 'min:1', 'max:1000000'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']],
            'store' => ['text' => ['required', 'string', 'max:10000'], 'target_count' => ['required', 'integer', 'min:1', 'max:1000000']],
            'update' => ['text' => ['sometimes', 'required', 'string', 'max:10000'], 'target_count' => ['sometimes', 'required', 'integer', 'min:1', 'max:1000000']],
            default => [],
        };
    }

    /** Reject an empty patch rather than pretending to update a Wird. */
    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);
        $validator->after(function (Validator $validator): void {
            if ($this->route()->getActionMethod() === 'update' && ! $this->exists('text') && ! $this->exists('target_count')) {
                $validator->errors()->add('text', 'Provide text or target_count to update.');
            }
        });
    }
}

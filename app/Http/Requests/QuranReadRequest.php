<?php

namespace App\Http\Requests;

use App\Http\Requests\Auth\StrictAuthRequest;
use App\Services\QuranService;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Validate public Quran query and route inputs independently, rejecting unexpected keys. */
class QuranReadRequest extends StrictAuthRequest
{
    /** @return array<string, array<mixed>> Metadata details take no query parameters. */
    public function rules(): array
    {
        if ($this->routeIs('api.v1.quran.surahs.show')) {
            return [];
        }
        $rules = ['page' => ['sometimes', 'required', 'integer', 'between:1,286'],
            'per_page' => ['sometimes', 'required', 'integer', 'between:1,100']];
        if ($this->routeIs('api.v1.quran.ayahs.index')) {
            $rules['edition'] = ['sometimes', 'required', 'string', 'max:32', Rule::in(QuranService::EDITIONS)];
        }
        if ($this->routeIs('api.v1.quran.audio.index')) {
            $rules['reciter'] = ['sometimes', 'required', 'string', 'max:100', Rule::in(QuranService::RECITERS)];
        }

        return $rules;
    }

    /** Validate the route separately so query/body fields can never replace a surah number. */
    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);
        $validator->after(function (Validator $validator): void {
            if ($this->route('surah') !== null) {
                $route = ValidatorFacade::make(['surah' => $this->route('surah')], ['surah' => ['required', 'integer', 'between:1,114']]);
                foreach ($route->errors()->get('surah') as $message) {
                    $validator->errors()->add('surah', $message);
                }
            }
        });
    }
}

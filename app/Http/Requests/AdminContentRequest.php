<?php

namespace App\Http\Requests;

use App\Http\Requests\Auth\StrictAuthRequest;
use App\Rules\PlainContentText;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/** Share strict validation and policy authorization across admin content endpoints. */
abstract class AdminContentRequest extends StrictAuthRequest
{
    protected const MODEL = Model::class;

    protected const PARAMETER = '';

    /** Resolve class-level or record-level policy checks before accepting input. */
    public function authorize(): bool
    {
        $action = $this->route()->getActionMethod();
        $subject = in_array($action, ['index', 'store'], true) ? static::MODEL : $this->route(static::PARAMETER);
        $ability = match ($action) {
            'index' => 'viewAny', 'show' => 'view', 'store' => 'create', 'destroy' => 'delete', default => 'update'
        };

        return Gate::allows($ability, $subject)
            && (! in_array($action, ['store', 'update'], true) || ! $this->exists('publication_status') || Gate::allows('publish', static::MODEL));
    }

    /** @return array<string, array<mixed>> Accept bounded lists, no detail input, and explicit write fields. */
    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'index' => $this->listRules(),
            'store', 'update' => $this->writeRules(),
            default => [],
        };
    }

    /** @return array<string, array<mixed>> Common pagination contract; subclasses may add fixed filters. */
    protected function listRules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1', 'max:1000000'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }

    /** @return array<string, array<mixed>> Define only editable fields for this resource. */
    abstract protected function writeRules(): array;

    /** @return array<mixed> Require complete POST/PUT input while permitting partial PATCH input. */
    protected function textRules(int $maximum, bool $nullable = false): array
    {
        return [...($this->isMethod('PATCH') || $nullable ? ['sometimes'] : []), $nullable ? 'nullable' : 'required', 'string', 'max:'.$maximum,
            new PlainContentText];
    }

    /** @return array<mixed> Restrict references to relative, non-executable public media paths. */
    protected function mediaRules(string $folder, string $extensions): array
    {
        return ['sometimes', 'nullable', 'string', 'max:255', 'regex:~^'.$folder.'/[A-Za-z0-9_-][A-Za-z0-9_./-]*\.('.$extensions.')$~D',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && (in_array('..', explode('/', $value), true) || in_array('.', explode('/', $value), true))) {
                    $fail('Use a relative public media path without traversal segments.');
                }
            }];
    }

    /** Reject an empty PATCH rather than claiming a successful edit. */
    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);
        $validator->after(function (Validator $validator): void {
            if ($this->isMethod('PATCH') && array_intersect(array_keys($this->all()), array_keys($this->writeRules())) === []) {
                $validator->errors()->add('content', 'Provide at least one editable field.');
            }
        });
    }
}

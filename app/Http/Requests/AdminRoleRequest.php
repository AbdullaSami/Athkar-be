<?php

namespace App\Http\Requests;

use App\Http\Requests\Auth\StrictAuthRequest;
use App\Rules\PlainContentText;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** Accept only bounded web-role management input from explicitly authorized administrators. */
class AdminRoleRequest extends StrictAuthRequest
{
    /** Require role-management permission before validating any role metadata. */
    public function authorize(): bool
    {
        if (! ($this->user()?->can('manage-roles') ?? false)) {
            return false;
        }
        $role = $this->route('role');
        abort_if($role instanceof Role && $role->guard_name !== 'web', 404);

        return true;
    }

    /** @return array<string, array<mixed>> Guard names and account assignments are never client-controlled. */
    public function rules(): array
    {
        $action = $this->route()->getActionMethod();
        if (in_array($action, ['index', 'permissions'], true)) {
            return ['page' => ['sometimes', 'integer', 'min:1', 'max:1000000'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
        }
        if (! in_array($action, ['store', 'update'], true)) {
            return [];
        }
        $unique = Rule::unique(Role::class, 'name')->where('guard_name', 'web');
        if ($this->route('role') instanceof Role) {
            $unique->ignore($this->route('role'));
        }
        $presence = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'required', 'string', 'max:100', new PlainContentText, $unique],
            'permissions' => [$this->isMethod('PATCH') ? 'sometimes' : 'present', 'array', 'list', 'max:100'],
            'permissions.*' => ['required', 'string', 'max:100', 'distinct:strict', Rule::exists(Permission::class, 'name')->where('guard_name', 'web')],
        ];
    }

    /** Reject empty PATCH requests rather than reporting a successful change. */
    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);
        $validator->after(function (Validator $validator): void {
            if ($this->isMethod('PATCH') && ! $this->exists('name') && ! $this->exists('permissions')) {
                $validator->errors()->add('role', 'Provide at least one editable field.');
            }
        });
    }
}

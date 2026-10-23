<?php

namespace App\Http\Requests;

use App\AccountStatus;
use App\Http\Requests\Auth\StrictAuthRequest;
use App\Models\User;
use App\Rules\PlainContentText;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role;

/** Validate account management and explicit role replacement without accepting ownership or permission injection. */
class AdminUserRequest extends StrictAuthRequest
{
    /** Reads require view-users; writes require manage-users and role replacement additionally requires manage-roles. */
    public function authorize(): bool
    {
        $action = $this->route()->getActionMethod();

        return ($this->user()?->can($action === 'show' ? 'view-users' : 'manage-users') ?? false)
            && (! ($action === 'roles' || ($action === 'store' && $this->exists('role_ids'))) || $this->user()->can('manage-roles'));
    }

    /** @return array<string, array<mixed>> Validate only the fields supported by the selected resource operation. */
    public function rules(): array
    {
        $action = $this->route()->getActionMethod();
        if ($action === 'roles') {
            return $this->roleRules(required: true);
        }
        if (! in_array($action, ['store', 'update'], true)) {
            return [];
        }
        $create = $action === 'store';
        $unique = Rule::unique(User::class, 'email');
        if ($this->route('user') instanceof User) {
            $unique->ignore($this->route('user'));
        }
        $presence = $create || $this->isMethod('PUT') ? 'required' : 'sometimes';
        $password = [...($create ? [] : ['sometimes']), ...$this->passwordRules(), 'min:12', 'confirmed'];

        return [
            ...($create ? $this->roleRules(required: false) : []),
            'name' => [$presence, 'required', 'string', 'max:160', new PlainContentText],
            'email' => [$presence, 'required', 'string', 'email:rfc', 'max:254', $unique],
            'age' => [$presence, 'required', 'integer', 'between:1,120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:16', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'account_status' => ['sometimes', 'required', Rule::enum(AccountStatus::class)],
            'password' => $password,
            'password_confirmation' => ['required_with:password', Rule::prohibitedIf(! $this->exists('password')), ...($create ? [] : ['sometimes']), ...$this->passwordRules()],
        ];
    }

    /** @return array<string, array<mixed>> Creation may omit roles; explicit replacement requires the array. */
    private function roleRules(bool $required): array
    {
        return ['role_ids' => [$required ? 'present' : 'sometimes', 'array', 'list', 'max:50'],
            'role_ids.*' => ['required', 'integer', 'min:1', 'max:999999999999999999', 'distinct', Rule::exists(Role::class, 'id')->where('guard_name', 'web')]];
    }

    /** Reject empty edits; password confirmation cannot stand in for an editable account field. */
    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);
        $validator->after(function (Validator $validator): void {
            if ($this->route()->getActionMethod() === 'update' && array_intersect(array_keys($this->all()), ['name', 'email', 'age', 'phone', 'password', 'account_status']) === []) {
                $validator->errors()->add('user', 'Provide at least one editable field.');
            }
        });
    }
}

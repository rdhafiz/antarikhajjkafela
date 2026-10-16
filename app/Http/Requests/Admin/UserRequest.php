<?php

namespace App\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Propaganistas\LaravelPhone\Rules\Phone;

class UserRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $creating = $user === null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:40', (new Phone)->international()->country('BD')],
            'password' => [$creating ? 'required' : 'nullable', 'confirmed', Password::defaults()],
            'is_active' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->whereNull('deleted_at'), $this->onlySuperAdminsGrantSuperAdmin()],
        ];
    }

    private function onlySuperAdminsGrantSuperAdmin(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if ($value === config('access.super_admin_role') && ! $this->user()->isSuperAdmin()) {
                $fail('Only a super admin can grant the super admin role.');
            }
        };
    }
}

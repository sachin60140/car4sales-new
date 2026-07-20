<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        return $employee instanceof User
            ? $this->user()->can('update', $employee)
            : $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        $employee = $this->route('employee');
        $creating = ! $employee instanceof User;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee?->id)->withoutTrashed()],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => $creating
                ? ['required', 'string', Password::defaults()]
                : ['nullable', 'string', Password::defaults()],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->withoutTrashed()],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->withoutTrashed()],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->withoutTrashed()],
            'is_active' => ['boolean'],
            'force_password_change' => ['boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],

            'profile.designation' => ['nullable', 'string', 'max:255'],
            'profile.date_of_joining' => ['nullable', 'date'],
            'profile.dob' => ['nullable', 'date', 'before:today'],
            'profile.gender' => ['nullable', 'string', 'max:20'],
            'profile.address' => ['nullable', 'string', 'max:255'],
            'profile.city' => ['nullable', 'string', 'max:100'],
            'profile.state' => ['nullable', 'string', 'max:100'],
            'profile.pin_code' => ['nullable', 'string', 'max:10'],
            'profile.emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'profile.emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'profile.blood_group' => ['nullable', 'string', 'max:10'],
            'profile.id_proof_type' => ['nullable', 'string', 'max:50'],
            'profile.id_proof_number' => ['nullable', 'string', 'max:50'],
            'profile.reports_to' => ['nullable', 'integer', Rule::exists('users', 'id')->withoutTrashed()],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Only Super Admins may grant the Super Admin role.
        if (! $this->user()->hasRole('Super Admin') && is_array($this->input('roles'))) {
            $this->merge([
                'roles' => array_values(array_diff($this->input('roles'), ['Super Admin'])),
            ]);
        }
    }
}

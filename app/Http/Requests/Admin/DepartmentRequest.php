<?php

namespace App\Http\Requests\Admin;

use App\Domain\Departments\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            ? $this->user()->can('update', $department)
            : $this->user()->can('create', Department::class);
    }

    public function rules(): array
    {
        $departmentId = $this->route('department')?->id;

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('departments', 'code')->ignore($departmentId)->withoutTrashed()],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function payload(): array
    {
        $data = $this->validated();
        $data['slug'] = Str::slug($data['name'].'-'.$data['code']);

        return $data;
    }
}

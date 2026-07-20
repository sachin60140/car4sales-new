<?php

namespace App\Http\Requests\Admin;

use App\Domain\Branches\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $branch = $this->route('branch');

        return $branch instanceof Branch
            ? $this->user()->can('update', $branch)
            : $this->user()->can('create', Branch::class);
    }

    public function rules(): array
    {
        $branchId = $this->route('branch')?->id;

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('branches', 'code')->ignore($branchId)->withoutTrashed()],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pin_code' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'gst_number' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
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

<?php

namespace App\Http\Requests\Admin;

use App\Domain\Teams\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team instanceof Team
            ? $this->user()->can('update', $team)
            : $this->user()->can('create', Team::class);
    }

    public function rules(): array
    {
        $teamId = $this->route('team')?->id;

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('teams', 'code')->ignore($teamId)->withoutTrashed()],
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->withoutTrashed()],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->withoutTrashed()],
            'team_leader_id' => ['nullable', 'integer', Rule::exists('users', 'id')->withoutTrashed()],
            'is_active' => ['boolean'],
        ];
    }
}

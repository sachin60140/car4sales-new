<?php

namespace App\Domain\RolesPermissions\Services;

use App\Domain\RolesPermissions\Enums\DataScope;
use App\Domain\RolesPermissions\Models\RoleMeta;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Translates a user's widest role data-scope into query constraints.
 *
 * Column mapping is provided per call because different modules store
 * ownership differently (branch_id / assigned_to / created_by ...).
 */
class ScopeService
{
    /**
     * Resolve the widest data scope across the user's roles.
     */
    public function scopeFor(User $user): DataScope
    {
        $roleIds = $user->roles->pluck('id');

        if ($roleIds->isEmpty()) {
            return DataScope::Own;
        }

        $scopes = RoleMeta::query()
            ->whereIn('role_id', $roleIds)
            ->pluck('data_scope');

        if ($scopes->isEmpty()) {
            return DataScope::Own;
        }

        return $scopes->sortByDesc(fn (DataScope $scope) => $scope->rank())->first();
    }

    /**
     * Branch ids visible to the user under selected_branches scopes.
     *
     * @return list<int>
     */
    public function selectedBranchIds(User $user): array
    {
        return RoleMeta::query()
            ->whereIn('role_id', $user->roles->pluck('id'))
            ->where('data_scope', DataScope::SelectedBranches)
            ->pluck('scope_branch_ids')
            ->flatten()
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Apply the user's data scope to a query.
     *
     * @param  array{branch?: string, department?: string, assigned?: string, owner?: string, team_users?: string}  $columns
     */
    public function apply(Builder $query, User $user, array $columns = []): Builder
    {
        $branchColumn = $columns['branch'] ?? 'branch_id';
        $assignedColumn = $columns['assigned'] ?? 'assigned_to';
        $ownerColumn = $columns['owner'] ?? 'created_by';

        return match ($this->scopeFor($user)) {
            DataScope::All, DataScope::ReadOnly => $query,
            DataScope::SelectedBranches => $query->whereIn($branchColumn, $this->selectedBranchIds($user)),
            DataScope::OwnBranch => $query->where($branchColumn, $user->branch_id),
            DataScope::OwnDepartment => $query->where($branchColumn, $user->branch_id)
                ->when($columns['department'] ?? null, fn (Builder $q, string $col) => $q->where($col, $user->department_id)),
            DataScope::OwnTeam => $query->whereIn(
                $assignedColumn,
                User::query()->where('team_id', $user->team_id ?? 0)->pluck('id'),
            ),
            DataScope::Assigned => $query->where($assignedColumn, $user->id),
            DataScope::Own => $query->where($ownerColumn, $user->id),
        };
    }

    /**
     * Scope specifically for user/employee listings.
     */
    public function applyToUsers(Builder $query, User $user): Builder
    {
        return match ($this->scopeFor($user)) {
            DataScope::All, DataScope::ReadOnly => $query,
            DataScope::SelectedBranches => $query->whereIn('branch_id', $this->selectedBranchIds($user)),
            DataScope::OwnBranch => $query->where('branch_id', $user->branch_id),
            DataScope::OwnDepartment => $query->where('branch_id', $user->branch_id)
                ->where('department_id', $user->department_id),
            DataScope::OwnTeam => $query->where('team_id', $user->team_id ?? 0),
            DataScope::Assigned, DataScope::Own => $query->where('id', $user->id),
        };
    }
}

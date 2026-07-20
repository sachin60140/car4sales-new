<?php

namespace App\Domain\Employees\Policies;

use App\Domain\RolesPermissions\Services\ScopeService;
use App\Models\User;

class EmployeePolicy
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function viewAny(User $user): bool
    {
        return $user->can('employees.view');
    }

    public function view(User $user, User $employee): bool
    {
        if (! $user->can('employees.view')) {
            return false;
        }

        return $this->scopes
            ->applyToUsers(User::query()->whereKey($employee->getKey()), $user)
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('employees.create');
    }

    public function update(User $user, User $employee): bool
    {
        if (! $user->can('employees.update')) {
            return false;
        }

        // Nobody edits a Super Admin except another Super Admin (handled by Gate::before).
        if ($employee->hasRole('Super Admin')) {
            return false;
        }

        return $this->scopes
            ->applyToUsers(User::query()->whereKey($employee->getKey()), $user)
            ->exists();
    }

    public function delete(User $user, User $employee): bool
    {
        if ($user->getKey() === $employee->getKey() || $employee->hasRole('Super Admin')) {
            return false;
        }

        return $user->can('employees.delete');
    }

    public function assign(User $user, User $employee): bool
    {
        return $user->can('employees.assign') && ! $employee->hasRole('Super Admin');
    }
}

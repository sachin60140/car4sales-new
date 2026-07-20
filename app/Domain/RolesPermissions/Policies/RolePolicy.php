<?php

namespace App\Domain\RolesPermissions\Policies;

use App\Domain\RolesPermissions\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.update') && $role->name !== 'Super Admin';
    }

    public function delete(User $user, Role $role): bool
    {
        if (! $user->can('roles.delete')) {
            return false;
        }

        // System roles cannot be deleted, only reconfigured.
        return ! ($role->meta?->is_system ?? false);
    }
}

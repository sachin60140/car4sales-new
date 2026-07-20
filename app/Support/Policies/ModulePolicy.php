<?php

namespace App\Support\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base policy mapping standard abilities to "<module>.<action>" permissions.
 * Super Admin bypasses via Gate::before in AppServiceProvider.
 */
abstract class ModulePolicy
{
    protected string $module;

    public function viewAny(User $user): bool
    {
        return $user->can("{$this->module}.view");
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can("{$this->module}.view");
    }

    public function create(User $user): bool
    {
        return $user->can("{$this->module}.create");
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can("{$this->module}.update");
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can("{$this->module}.delete");
    }

    public function export(User $user): bool
    {
        return $user->can("{$this->module}.export");
    }
}

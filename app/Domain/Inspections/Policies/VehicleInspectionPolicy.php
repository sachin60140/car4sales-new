<?php

namespace App\Domain\Inspections\Policies;

use App\Domain\Inspections\Models\VehicleInspection;
use App\Models\User;

class VehicleInspectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inspections.view');
    }

    public function view(User $user, VehicleInspection $inspection): bool
    {
        if (! $user->can('inspections.view')) {
            return false;
        }

        // Inspectors on the assigned scope only see their own inspections.
        if ($user->hasRole('Inspector') && ! $user->hasAnyRole(['Super Admin', 'Purchase Manager', 'Branch Manager'])) {
            return $inspection->inspector_id === $user->id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('inspections.create');
    }

    public function update(User $user, VehicleInspection $inspection): bool
    {
        if ($inspection->isLocked()) {
            return false;
        }

        if ($user->hasRole('Inspector') && ! $user->hasRole('Super Admin')) {
            return $user->can('inspections.update') && $inspection->inspector_id === $user->id;
        }

        return $user->can('inspections.update');
    }

    public function review(User $user, VehicleInspection $inspection): bool
    {
        return $user->can('inspections.approve');
    }
}

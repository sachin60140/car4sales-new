<?php

namespace App\Domain\Inventory\Policies;

use App\Domain\Inventory\Models\Vehicle;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Models\User;

class VehiclePolicy
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function viewAny(User $user): bool
    {
        return $user->can('vehicles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('vehicles.create');
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->can('vehicles.view') && $this->inScope($user, $vehicle);
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->can('vehicles.update') && $this->inScope($user, $vehicle);
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->can('vehicles.delete') && $this->inScope($user, $vehicle);
    }

    public function viewPurchaseCost(User $user): bool
    {
        return $user->can('vehicles.view-purchase-cost');
    }

    private function inScope(User $user, Vehicle $vehicle): bool
    {
        return $this->scopes
            ->apply(Vehicle::query()->whereKey($vehicle->getKey()), $user, [
                'branch' => 'branch_id',
                'owner' => 'created_by',
            ])
            ->exists();
    }
}

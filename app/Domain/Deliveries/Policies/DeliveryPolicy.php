<?php

namespace App\Domain\Deliveries\Policies;

use App\Domain\Deliveries\Models\Delivery;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Models\User;

class DeliveryPolicy
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function viewAny(User $user): bool
    {
        return $user->can('deliveries.view');
    }

    public function view(User $user, Delivery $delivery): bool
    {
        return $user->can('deliveries.view') && $this->inScope($user, $delivery);
    }

    public function create(User $user): bool
    {
        return $user->can('deliveries.create');
    }

    public function update(User $user, Delivery $delivery): bool
    {
        return $user->can('deliveries.update') && $this->inScope($user, $delivery);
    }

    public function approve(User $user, Delivery $delivery): bool
    {
        return $user->can('deliveries.approve') && $this->inScope($user, $delivery);
    }

    public function print(User $user, Delivery $delivery): bool
    {
        return $user->can('deliveries.print') && $this->inScope($user, $delivery);
    }

    private function inScope(User $user, Delivery $delivery): bool
    {
        return $this->scopes->apply(Delivery::query()->whereKey($delivery->getKey()), $user, [
            'branch' => 'branch_id', 'owner' => 'created_by',
        ])->exists()
            || $delivery->created_by === $user->id;
    }
}

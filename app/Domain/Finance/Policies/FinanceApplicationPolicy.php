<?php

namespace App\Domain\Finance\Policies;

use App\Domain\Finance\Models\FinanceApplication;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Models\User;

class FinanceApplicationPolicy
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function viewAny(User $user): bool
    {
        return $user->can('finance.view');
    }

    public function view(User $user, FinanceApplication $application): bool
    {
        return $user->can('finance.view') && $this->inScope($user, $application);
    }

    public function create(User $user): bool
    {
        return $user->can('finance.create');
    }

    public function update(User $user, FinanceApplication $application): bool
    {
        return $user->can('finance.update') && $this->inScope($user, $application);
    }

    public function approve(User $user, FinanceApplication $application): bool
    {
        return $user->can('finance.approve') && $this->inScope($user, $application);
    }

    private function inScope(User $user, FinanceApplication $application): bool
    {
        return $this->scopes->apply(FinanceApplication::query()->whereKey($application->getKey()), $user, [
            'branch' => 'branch_id', 'assigned' => 'assigned_to', 'owner' => 'created_by',
        ])->exists()
            || $application->assigned_to === $user->id
            || $application->created_by === $user->id;
    }
}

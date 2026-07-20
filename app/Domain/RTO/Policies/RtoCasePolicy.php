<?php

namespace App\Domain\RTO\Policies;

use App\Domain\RolesPermissions\Services\ScopeService;
use App\Domain\RTO\Models\RtoCase;
use App\Models\User;

class RtoCasePolicy
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function viewAny(User $user): bool
    {
        return $user->can('rto-cases.view');
    }

    public function view(User $user, RtoCase $case): bool
    {
        return $user->can('rto-cases.view') && $this->inScope($user, $case);
    }

    public function create(User $user): bool
    {
        return $user->can('rto-cases.create');
    }

    public function update(User $user, RtoCase $case): bool
    {
        return $user->can('rto-cases.update') && $this->inScope($user, $case);
    }

    public function assign(User $user, RtoCase $case): bool
    {
        return $user->can('rto-cases.assign') && $this->inScope($user, $case);
    }

    public function approve(User $user, RtoCase $case): bool
    {
        return $user->can('rto-cases.approve') && $this->inScope($user, $case);
    }

    public function print(User $user, RtoCase $case): bool
    {
        return $user->can('rto-cases.print') && $this->inScope($user, $case);
    }

    private function inScope(User $user, RtoCase $case): bool
    {
        return $this->scopes->apply(RtoCase::query()->whereKey($case->getKey()), $user, [
            'branch' => 'branch_id', 'assigned' => 'assigned_to', 'owner' => 'created_by',
        ])->exists()
            || $case->assigned_to === $user->id
            || $case->created_by === $user->id;
    }
}

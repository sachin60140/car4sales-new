<?php

namespace App\Domain\Refurbishment\Policies;

use App\Domain\Refurbishment\Models\WorkshopJob;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Models\User;

class WorkshopJobPolicy
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function viewAny(User $user): bool
    {
        return $user->can('refurbishment.view');
    }

    public function view(User $user, WorkshopJob $job): bool
    {
        return $user->can('refurbishment.view') && $this->inScope($user, $job);
    }

    public function create(User $user): bool
    {
        return $user->can('refurbishment.create');
    }

    public function update(User $user, WorkshopJob $job): bool
    {
        return $user->can('refurbishment.update') && $this->inScope($user, $job);
    }

    public function approve(User $user, WorkshopJob $job): bool
    {
        return $user->can('refurbishment.approve') && $this->inScope($user, $job);
    }

    private function inScope(User $user, WorkshopJob $job): bool
    {
        return $this->scopes
            ->apply(WorkshopJob::query()->whereKey($job->getKey()), $user, [
                'branch' => 'branch_id', 'owner' => 'created_by',
            ])
            ->exists();
    }
}

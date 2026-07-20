<?php

namespace App\Domain\SalesLeads\Policies;

use App\Domain\RolesPermissions\Services\ScopeService;
use App\Domain\SalesLeads\Models\SalesLead;
use App\Models\User;

class SalesLeadPolicy
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function viewAny(User $user): bool
    {
        return $user->can('sales-leads.view');
    }

    public function view(User $user, SalesLead $lead): bool
    {
        return $user->can('sales-leads.view') && $this->inScope($user, $lead);
    }

    public function create(User $user): bool
    {
        return $user->can('sales-leads.create');
    }

    public function update(User $user, SalesLead $lead): bool
    {
        return $user->can('sales-leads.update') && $this->inScope($user, $lead);
    }

    public function assign(User $user, SalesLead $lead): bool
    {
        return $user->can('sales-leads.assign') && $this->inScope($user, $lead);
    }

    private function inScope(User $user, SalesLead $lead): bool
    {
        // Telecaller / sales executive scoping covers either assignment column.
        return $this->scopes->apply(
            SalesLead::query()->whereKey($lead->getKey()),
            $user,
            ['branch' => 'branch_id', 'assigned' => 'telecaller_id', 'owner' => 'created_by'],
        )->exists()
            || $lead->telecaller_id === $user->id
            || $lead->sales_executive_id === $user->id;
    }
}

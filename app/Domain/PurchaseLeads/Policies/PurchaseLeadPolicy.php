<?php

namespace App\Domain\PurchaseLeads\Policies;

use App\Domain\PurchaseLeads\Models\PurchaseLead;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Models\User;

class PurchaseLeadPolicy
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function viewAny(User $user): bool
    {
        return $user->can('purchase-leads.view');
    }

    public function view(User $user, PurchaseLead $lead): bool
    {
        return $user->can('purchase-leads.view') && $this->inScope($user, $lead);
    }

    public function create(User $user): bool
    {
        return $user->can('purchase-leads.create');
    }

    public function update(User $user, PurchaseLead $lead): bool
    {
        return $user->can('purchase-leads.update') && $this->inScope($user, $lead);
    }

    public function assign(User $user, PurchaseLead $lead): bool
    {
        return $user->can('purchase-leads.assign') && $this->inScope($user, $lead);
    }

    public function delete(User $user, PurchaseLead $lead): bool
    {
        return $user->can('purchase-leads.delete') && $this->inScope($user, $lead);
    }

    public function viewKyc(User $user): bool
    {
        return $user->can('sellers.view-kyc');
    }

    private function inScope(User $user, PurchaseLead $lead): bool
    {
        return $this->scopes
            ->apply(PurchaseLead::query()->whereKey($lead->getKey()), $user, [
                'branch' => 'branch_id',
                'assigned' => 'assigned_to',
                'owner' => 'created_by',
            ])
            ->exists();
    }
}

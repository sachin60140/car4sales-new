<?php

namespace App\Domain\Approvals\Policies;

use App\Domain\Approvals\Models\ApprovalRequest;
use App\Models\User;

class ApprovalRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('approvals.view');
    }

    public function view(User $user, ApprovalRequest $request): bool
    {
        return $user->can('approvals.view');
    }

    /**
     * A user may act on an approval when they hold the current step's role
     * (or approvals.approve as a manager) and the request is still pending.
     */
    public function decide(User $user, ApprovalRequest $request): bool
    {
        if (! $request->status->isOpen()) {
            return false;
        }

        if ($user->hasRole('Super Admin')) {
            return true;
        }

        $step = $request->currentStep();

        if ($step?->role_id === null) {
            return $user->can('approvals.approve');
        }

        return $user->can('approvals.approve')
            && $step->role !== null
            && $user->hasRole($step->role->name);
    }
}

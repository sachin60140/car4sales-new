<?php

namespace App\Domain\Bookings\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Resolves how much discount a user can authorise without escalation, from the
 * approval_limits configured for the 'discounts' module.
 */
class DiscountAuthority
{
    /** Role escalation chain for discount approvals. */
    public const CHAIN = ['Sales Manager', 'Branch Manager', 'Director'];

    public function forUser(User $user): float
    {
        if ($user->hasRole('Super Admin')) {
            return INF;
        }

        $roleIds = $user->roles->pluck('id');

        if ($roleIds->isEmpty()) {
            return 0.0;
        }

        $limits = DB::table('approval_limits')
            ->whereIn('role_id', $roleIds)
            ->where('module', 'discounts')
            ->pluck('max_amount');

        if ($limits->isEmpty()) {
            return 0.0;
        }

        // A null max_amount means unlimited authority.
        if ($limits->contains(null)) {
            return INF;
        }

        return (float) $limits->max();
    }

    /**
     * Does this booking need discount approval when confirmed by $user?
     * Approval is required when the discount exceeds the user's authority, or the
     * selling price dips below the vehicle's minimum selling price.
     */
    public function requiresApproval(User $user, float $discount, float $sellingPrice, ?float $minSellingPrice): bool
    {
        if ($discount > $this->forUser($user)) {
            return true;
        }

        return $minSellingPrice !== null && $sellingPrice < $minSellingPrice;
    }
}

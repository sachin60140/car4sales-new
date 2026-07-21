<?php

namespace App\Domain\VendorSubmissions\Policies;

use App\Domain\VendorSubmissions\Models\VendorProfile;
use App\Models\User;

class VendorProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('vendor-partners.view');
    }

    public function view(User $user, VendorProfile $profile): bool
    {
        return $user->can('vendor-partners.view') || $profile->user_id === $user->id;
    }

    public function activate(User $user, VendorProfile $profile): bool
    {
        return $user->can('vendor-partners.activate');
    }
}

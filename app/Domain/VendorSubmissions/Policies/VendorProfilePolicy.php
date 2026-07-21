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

    public function create(User $user): bool
    {
        return $user->can('vendor-partners.create');
    }

    public function update(User $user, VendorProfile $profile): bool
    {
        return $user->can('vendor-partners.update');
    }

    public function activate(User $user, VendorProfile $profile): bool
    {
        return $user->can('vendor-partners.activate');
    }
}

<?php

namespace App\Domain\RolesPermissions\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    public function meta(): HasOne
    {
        return $this->hasOne(RoleMeta::class);
    }

    public function approvalLimits(): HasMany
    {
        return $this->hasMany(ApprovalLimit::class);
    }
}

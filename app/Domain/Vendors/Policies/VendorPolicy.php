<?php

namespace App\Domain\Vendors\Policies;

use App\Support\Policies\ModulePolicy;

class VendorPolicy extends ModulePolicy
{
    protected string $module = 'vendors';
}

<?php

namespace App\Domain\Customers\Policies;

use App\Support\Policies\ModulePolicy;

class CustomerPolicy extends ModulePolicy
{
    protected string $module = 'customers';
}

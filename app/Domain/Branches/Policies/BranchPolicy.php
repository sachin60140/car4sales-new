<?php

namespace App\Domain\Branches\Policies;

use App\Support\Policies\ModulePolicy;

class BranchPolicy extends ModulePolicy
{
    protected string $module = 'branches';
}

<?php

namespace App\Support\Workflow;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;

interface Transitionable
{
    public function statusColumnName(): string;

    public function currentStatus(): HasTransitions;

    public function statusHistories(): HasMany;

    public function writeStatusHistory(?string $from, string $to, ?User $user, ?string $remarks): void;
}

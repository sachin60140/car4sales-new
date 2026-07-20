<?php

namespace App\Support\Workflow;

/**
 * Implemented by every status enum. Defines the allowed-transition map and labels.
 */
interface HasTransitions
{
    /**
     * Statuses this state may transition to.
     *
     * @return array<int, static>
     */
    public function allowedTransitions(): array;

    public function label(): string;

    public function canTransitionTo(self $target): bool;
}

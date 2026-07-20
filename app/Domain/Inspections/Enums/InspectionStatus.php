<?php

namespace App\Domain\Inspections\Enums;

use App\Support\Workflow\HasTransitions;

enum InspectionStatus: string implements HasTransitions
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Reviewed = 'reviewed';
    case Cancelled = 'cancelled';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Scheduled => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Submitted, self::Cancelled],
            self::Submitted => [self::Reviewed, self::InProgress],
            self::Reviewed => [],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(HasTransitions $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}

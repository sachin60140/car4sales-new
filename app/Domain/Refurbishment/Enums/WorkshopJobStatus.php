<?php

namespace App\Domain\Refurbishment\Enums;

use App\Support\Workflow\HasTransitions;

enum WorkshopJobStatus: string implements HasTransitions
{
    case Draft = 'draft';
    case ApprovalPending = 'approval_pending';
    case Approved = 'approved';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case QcPassed = 'qc_passed';
    case QcFailed = 'qc_failed';
    case Cancelled = 'cancelled';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::ApprovalPending, self::Approved, self::Cancelled],
            self::ApprovalPending => [self::Approved, self::Cancelled, self::Draft],
            self::Approved => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Completed, self::Cancelled],
            self::Completed => [self::QcPassed, self::QcFailed],
            self::QcFailed => [self::InProgress],
            self::QcPassed => [],
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

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}

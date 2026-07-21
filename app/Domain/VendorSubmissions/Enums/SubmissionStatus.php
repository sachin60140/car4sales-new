<?php

namespace App\Domain\VendorSubmissions\Enums;

use App\Support\Workflow\HasTransitions;

enum SubmissionStatus: string implements HasTransitions
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingReview],
            self::PendingReview => [self::Approved, self::Rejected, self::Draft],
            self::Rejected => [self::PendingReview, self::Draft],
            self::Approved => [],
        };
    }

    public function canTransitionTo(HasTransitions $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this === self::Approved;
    }

    public function isEditableByVendor(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
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

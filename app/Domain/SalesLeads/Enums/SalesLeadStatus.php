<?php

namespace App\Domain\SalesLeads\Enums;

use App\Support\Workflow\HasTransitions;

enum SalesLeadStatus: string implements HasTransitions
{
    case New = 'new';
    case Assigned = 'assigned';
    case Contacted = 'contacted';
    case Interested = 'interested';
    case FollowUp = 'follow_up';
    case VisitScheduled = 'visit_scheduled';
    case VisitCompleted = 'visit_completed';
    case TestDrive = 'test_drive';
    case Negotiation = 'negotiation';
    case Booking = 'booking';
    case FinanceProcessing = 'finance_processing';
    case DeliveryPending = 'delivery_pending';
    case Delivered = 'delivered';
    case Lost = 'lost';
    case WrongNumber = 'wrong_number';
    case Duplicate = 'duplicate';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::Assigned, self::Contacted, self::WrongNumber, self::Duplicate, self::Lost],
            self::Assigned => [self::Contacted, self::WrongNumber, self::Duplicate, self::Lost],
            self::Contacted => [self::Interested, self::FollowUp, self::WrongNumber, self::Lost],
            self::Interested => [self::FollowUp, self::VisitScheduled, self::TestDrive, self::Negotiation, self::Lost],
            self::FollowUp => [self::Interested, self::Contacted, self::VisitScheduled, self::Negotiation, self::Lost],
            self::VisitScheduled => [self::VisitCompleted, self::FollowUp, self::Lost],
            self::VisitCompleted => [self::TestDrive, self::Negotiation, self::FollowUp, self::Lost],
            self::TestDrive => [self::Negotiation, self::Booking, self::FollowUp, self::Lost],
            self::Negotiation => [self::Booking, self::FollowUp, self::Lost],
            self::Booking => [self::FinanceProcessing, self::DeliveryPending, self::Lost],
            self::FinanceProcessing => [self::DeliveryPending, self::Lost],
            self::DeliveryPending => [self::Delivered, self::Lost],
            self::Delivered => [],
            self::Lost, self::WrongNumber, self::Duplicate => [self::Contacted],
        };
    }

    public function canTransitionTo(HasTransitions $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this === self::Delivered;
    }

    /** Statuses that count as a "lost" outcome and require a lost reason. */
    public function isLost(): bool
    {
        return in_array($this, [self::Lost, self::WrongNumber, self::Duplicate], true);
    }

    /** Open (workable) leads that appear in the telecaller queue. */
    public static function openValues(): array
    {
        return [
            self::New->value, self::Assigned->value, self::Contacted->value, self::Interested->value,
            self::FollowUp->value, self::VisitScheduled->value, self::VisitCompleted->value,
            self::TestDrive->value, self::Negotiation->value,
        ];
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

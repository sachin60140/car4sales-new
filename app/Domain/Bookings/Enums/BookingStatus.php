<?php

namespace App\Domain\Bookings\Enums;

use App\Support\Workflow\HasTransitions;

enum BookingStatus: string implements HasTransitions
{
    case Draft = 'draft';
    case ApprovalPending = 'approval_pending';
    case Confirmed = 'confirmed';
    case PaymentPending = 'payment_pending';
    case FinancePending = 'finance_pending';
    case ReadyForDelivery = 'ready_for_delivery';
    case Delivered = 'delivered';
    case CancellationRequested = 'cancellation_requested';
    case Cancelled = 'cancelled';
    case RefundPending = 'refund_pending';
    case Refunded = 'refunded';
    case Forfeited = 'forfeited';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::ApprovalPending, self::Confirmed, self::Cancelled],
            self::ApprovalPending => [self::Confirmed, self::Cancelled, self::Draft],
            self::Confirmed => [self::PaymentPending, self::FinancePending, self::ReadyForDelivery, self::CancellationRequested],
            self::PaymentPending => [self::ReadyForDelivery, self::FinancePending, self::CancellationRequested],
            self::FinancePending => [self::ReadyForDelivery, self::PaymentPending, self::CancellationRequested],
            self::ReadyForDelivery => [self::Delivered, self::CancellationRequested],
            self::Delivered => [],
            self::CancellationRequested => [self::Cancelled, self::Confirmed],
            self::Cancelled => [self::RefundPending, self::Forfeited],
            self::RefundPending => [self::Refunded],
            self::Refunded => [],
            self::Forfeited => [],
        };
    }

    public function canTransitionTo(HasTransitions $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Statuses in which the vehicle is held (reserved/booked). */
    public function holdsVehicle(): bool
    {
        return in_array($this, [
            self::Confirmed, self::PaymentPending, self::FinancePending,
            self::ReadyForDelivery, self::Delivered, self::CancellationRequested,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Refunded, self::Forfeited], true);
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

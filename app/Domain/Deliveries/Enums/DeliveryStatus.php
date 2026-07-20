<?php

namespace App\Domain\Deliveries\Enums;

enum DeliveryStatus: string
{
    case ApprovalPending = 'approval_pending';
    case Approved = 'approved';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

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

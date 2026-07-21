<?php

namespace App\Domain\VendorSubmissions\Enums;

enum SettlementStatus: string
{
    case NotStarted = 'not_started';
    case AgreementReady = 'agreement_ready';
    case PaymentRequested = 'payment_requested';
    case Paid = 'paid';

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

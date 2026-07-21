<?php

namespace App\Domain\VendorSubmissions\Enums;

/**
 * Post-approval settlement lifecycle for a vendor submission:
 *   not_started → kyc_pending → kyc_submitted → agreement_ready
 *   → payment_requested → paid
 *
 * kyc_pending/kyc_submitted cover the owner-details + document-verification stage
 * that must clear before the (dynamic) agreement is issued and payment can flow.
 */
enum SettlementStatus: string
{
    case NotStarted = 'not_started';
    case KycPending = 'kyc_pending';
    case KycSubmitted = 'kyc_submitted';
    case AgreementReady = 'agreement_ready';
    case PaymentRequested = 'payment_requested';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not started',
            self::KycPending => 'Owner details pending',
            self::KycSubmitted => 'Documents under review',
            self::AgreementReady => 'Agreement ready',
            self::PaymentRequested => 'Payment requested',
            self::Paid => 'Paid',
        };
    }

    /** The agreement + payment flow is only available once owner documents are approved. */
    public function agreementAvailable(): bool
    {
        return in_array($this, [self::AgreementReady, self::PaymentRequested, self::Paid], true);
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}

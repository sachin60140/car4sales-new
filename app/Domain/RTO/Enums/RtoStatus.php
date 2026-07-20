<?php

namespace App\Domain\RTO\Enums;

use App\Support\Workflow\HasTransitions;

enum RtoStatus: string implements HasTransitions
{
    case CaseCreated = 'case_created';
    case SellerDocumentsPending = 'seller_documents_pending';
    case BuyerDocumentsPending = 'buyer_documents_pending';
    case SignaturePending = 'signature_pending';
    case NocRequired = 'noc_required';
    case NocApplied = 'noc_applied';
    case NocReceived = 'noc_received';
    case FormsPrepared = 'forms_prepared';
    case Submitted = 'submitted';
    case AppearancePending = 'appearance_pending';
    case ObjectionRaised = 'objection_raised';
    case ObjectionResolved = 'objection_resolved';
    case TransferApproved = 'transfer_approved';
    case RcPrinted = 'rc_printed';
    case RcReceived = 'rc_received';
    case RcHandedOver = 'rc_handed_over';
    case Closed = 'closed';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::CaseCreated => [self::SellerDocumentsPending, self::BuyerDocumentsPending],
            self::SellerDocumentsPending => [self::BuyerDocumentsPending, self::SignaturePending],
            self::BuyerDocumentsPending => [self::SignaturePending, self::SellerDocumentsPending],
            self::SignaturePending => [self::NocRequired, self::FormsPrepared],
            self::NocRequired => [self::NocApplied, self::FormsPrepared],
            self::NocApplied => [self::NocReceived],
            self::NocReceived => [self::FormsPrepared],
            self::FormsPrepared => [self::Submitted],
            self::Submitted => [self::AppearancePending, self::ObjectionRaised, self::TransferApproved],
            self::AppearancePending => [self::TransferApproved, self::ObjectionRaised],
            self::ObjectionRaised => [self::ObjectionResolved],
            self::ObjectionResolved => [self::Submitted, self::TransferApproved],
            self::TransferApproved => [self::RcPrinted],
            self::RcPrinted => [self::RcReceived],
            self::RcReceived => [self::RcHandedOver],
            self::RcHandedOver => [self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(HasTransitions $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this === self::Closed;
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

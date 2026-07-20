<?php

namespace App\Domain\PurchaseLeads\Enums;

use App\Support\Workflow\HasTransitions;

enum PurchaseLeadStatus: string implements HasTransitions
{
    case New = 'new';
    case Contacted = 'contacted';
    case InspectionScheduled = 'inspection_scheduled';
    case InspectionCompleted = 'inspection_completed';
    case DocumentVerificationPending = 'document_verification_pending';
    case SellerKycPending = 'seller_kyc_pending';
    case ValuationPending = 'valuation_pending';
    case Negotiation = 'negotiation';
    case PurchaseApprovalPending = 'purchase_approval_pending';
    case PurchaseApproved = 'purchase_approved';
    case AgreementPending = 'agreement_pending';
    case PaymentPending = 'payment_pending';
    case PossessionPending = 'possession_pending';
    case Purchased = 'purchased';
    case Rejected = 'rejected';
    case SellerNotInterested = 'seller_not_interested';
    case VehicleSoldElsewhere = 'vehicle_sold_elsewhere';
    case Closed = 'closed';

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::Contacted, self::Rejected, self::SellerNotInterested, self::Closed],
            self::Contacted => [self::InspectionScheduled, self::Negotiation, self::SellerNotInterested, self::VehicleSoldElsewhere, self::Closed],
            self::InspectionScheduled => [self::InspectionCompleted, self::Contacted, self::Closed],
            self::InspectionCompleted => [self::DocumentVerificationPending, self::Rejected, self::Closed],
            self::DocumentVerificationPending => [self::SellerKycPending, self::Rejected],
            self::SellerKycPending => [self::ValuationPending, self::Rejected],
            self::ValuationPending => [self::Negotiation, self::Rejected],
            self::Negotiation => [self::PurchaseApprovalPending, self::SellerNotInterested, self::VehicleSoldElsewhere],
            self::PurchaseApprovalPending => [self::PurchaseApproved, self::Rejected, self::Negotiation],
            self::PurchaseApproved => [self::AgreementPending],
            self::AgreementPending => [self::PaymentPending],
            self::PaymentPending => [self::PossessionPending],
            self::PossessionPending => [self::Purchased],
            self::Purchased => [self::Closed],
            self::Rejected, self::SellerNotInterested, self::VehicleSoldElsewhere => [self::Contacted, self::Closed],
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

    public function isLost(): bool
    {
        return in_array($this, [self::Rejected, self::SellerNotInterested, self::VehicleSoldElsewhere], true);
    }

    /**
     * Statuses that must only be reached through their dedicated action, because
     * that action creates a downstream record:
     *   PurchaseApproved → the purchase-approval decision creates the VehiclePurchase.
     *   Purchased        → Confirm Possession creates the stock (inventory) entry.
     * Reaching these via the generic status control would orphan the lead (marked
     * done with no purchase / no stock), so the generic transition must refuse them.
     */
    public function requiresDedicatedAction(): bool
    {
        return in_array($this, [self::PurchaseApproved, self::Purchased], true);
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

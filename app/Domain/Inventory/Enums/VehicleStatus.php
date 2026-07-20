<?php

namespace App\Domain\Inventory\Enums;

use App\Support\Workflow\HasTransitions;

enum VehicleStatus: string implements HasTransitions
{
    case PurchasePending = 'purchase_pending';
    case DocumentsPending = 'documents_pending';
    case InwardPending = 'inward_pending';
    case InStock = 'in_stock';
    case InspectionPending = 'inspection_pending';
    case UnderRefurbishment = 'under_refurbishment';
    case ReadyForSale = 'ready_for_sale';
    case Published = 'published';
    case Reserved = 'reserved';
    case Booked = 'booked';
    case DeliveryPending = 'delivery_pending';
    case Delivered = 'delivered';
    case WholesaleSold = 'wholesale_sold';
    case Returned = 'returned';
    case Blocked = 'blocked';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PurchasePending => [self::DocumentsPending, self::InwardPending, self::InStock, self::Blocked],
            self::DocumentsPending => [self::InwardPending, self::InStock, self::Blocked],
            self::InwardPending => [self::InStock, self::Blocked],
            self::InStock => [self::InspectionPending, self::UnderRefurbishment, self::ReadyForSale, self::Blocked],
            self::InspectionPending => [self::UnderRefurbishment, self::ReadyForSale, self::InStock],
            self::UnderRefurbishment => [self::ReadyForSale, self::InStock, self::Blocked],
            self::ReadyForSale => [self::Published, self::Reserved, self::Booked, self::UnderRefurbishment, self::Blocked],
            self::Published => [self::Reserved, self::Booked, self::ReadyForSale, self::Blocked],
            self::Reserved => [self::Booked, self::ReadyForSale, self::Published],
            self::Booked => [self::DeliveryPending, self::ReadyForSale, self::WholesaleSold],
            self::DeliveryPending => [self::Delivered, self::Booked],
            self::Delivered => [self::Returned],
            self::WholesaleSold => [self::Returned],
            self::Returned => [self::InStock, self::ReadyForSale],
            self::Blocked => [self::InStock],
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

<?php

namespace App\Domain\Payments\Enums;

enum LedgerHead: string
{
    case SellingPrice = 'selling_price';
    case BookingAmount = 'booking_amount';
    case DownPayment = 'down_payment';
    case FinanceAmount = 'finance_amount';
    case Exchange = 'exchange';
    case Discount = 'discount';
    case Insurance = 'insurance';
    case Accessories = 'accessories';
    case RtoCharges = 'rto_charges';
    case Other = 'other';
    case Payment = 'payment';
    case Refund = 'refund';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}

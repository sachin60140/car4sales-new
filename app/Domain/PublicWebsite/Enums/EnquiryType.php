<?php

namespace App\Domain\PublicWebsite\Enums;

enum EnquiryType: string
{
    case Vehicle = 'vehicle';
    case TestDrive = 'test_drive';
    case Finance = 'finance';
    case Callback = 'callback';
    case Contact = 'contact';
    case SellCar = 'sell_car';
    case BookingInterest = 'booking_interest';

    public function label(): string
    {
        return match ($this) {
            self::Vehicle => 'Vehicle Enquiry',
            self::TestDrive => 'Test Drive Request',
            self::Finance => 'Finance Enquiry',
            self::Callback => 'Callback Request',
            self::Contact => 'Contact Message',
            self::SellCar => 'Sell Your Car',
            self::BookingInterest => 'Booking Interest',
        };
    }

    /** Enquiry types that should become a sales lead (Phase 5 wiring). */
    public function createsSalesLead(): bool
    {
        return in_array($this, [self::Vehicle, self::TestDrive, self::Finance, self::Callback, self::BookingInterest], true);
    }
}

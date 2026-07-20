<?php

namespace App\Domain\SalesLeads\Enums;

enum SalesLeadSource: string
{
    case Website = 'website';
    case MobileApp = 'mobile_app';
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case GoogleAds = 'google_ads';
    case WhatsApp = 'whatsapp';
    case WalkIn = 'walk_in';
    case Referral = 'referral';
    case ExistingCustomer = 'existing_customer';
    case Marketplace = 'marketplace';
    case Dealer = 'dealer';
    case Campaign = 'campaign';
    case Manual = 'manual';

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

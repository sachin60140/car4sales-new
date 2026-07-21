<?php

namespace App\Domain\VendorSubmissions\Enums;

enum VendorProfileStatus: string
{
    case PendingActivation = 'pending_activation';
    case Active = 'active';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    /** Whether the vendor may submit vehicles. */
    public function canSubmit(): bool
    {
        return $this === self::Active;
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}

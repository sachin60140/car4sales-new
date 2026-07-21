<?php

namespace App\Domain\VendorSubmissions\Enums;

enum ChecklistResult: string
{
    case Pass = 'pass';
    case Fail = 'fail';
    case Na = 'na';

    public function label(): string
    {
        return $this === self::Na ? 'N/A' : ucfirst($this->value);
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}

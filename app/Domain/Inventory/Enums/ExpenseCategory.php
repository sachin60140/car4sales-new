<?php

namespace App\Domain\Inventory\Enums;

enum ExpenseCategory: string
{
    case Refurbishment = 'refurbishment';
    case Rto = 'rto';
    case Documentation = 'documentation';
    case Transportation = 'transportation';
    case Insurance = 'insurance';
    case Parts = 'parts';
    case Labour = 'labour';
    case Brokerage = 'brokerage';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}

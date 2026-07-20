<?php

namespace App\Domain\Inventory\Enums;

enum MovementType: string
{
    case BranchTransfer = 'branch_transfer';
    case Workshop = 'workshop';
    case TestDrive = 'test_drive';
    case Rto = 'rto';
    case Parking = 'parking';

    public function label(): string
    {
        return match ($this) {
            self::BranchTransfer => 'Branch Transfer',
            self::Workshop => 'Workshop Movement',
            self::TestDrive => 'Test Drive',
            self::Rto => 'RTO Movement',
            self::Parking => 'Parking Change',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $m) => ['value' => $m->value, 'label' => $m->label()], self::cases());
    }
}

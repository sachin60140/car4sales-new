<?php

namespace App\Domain\RolesPermissions\Enums;

enum DataScope: string
{
    case All = 'all';
    case SelectedBranches = 'selected_branches';
    case OwnBranch = 'own_branch';
    case OwnDepartment = 'own_department';
    case OwnTeam = 'own_team';
    case Assigned = 'assigned';
    case Own = 'own';
    case ReadOnly = 'read_only';

    /**
     * Rank used to pick the most permissive scope among a user's roles.
     * Higher rank = wider visibility.
     */
    public function rank(): int
    {
        return match ($this) {
            self::All => 80,
            self::SelectedBranches => 70,
            self::OwnBranch => 60,
            self::OwnDepartment => 50,
            self::OwnTeam => 40,
            self::Assigned => 30,
            self::Own => 20,
            self::ReadOnly => 10,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::All => 'All Company Records',
            self::SelectedBranches => 'Selected Branches',
            self::OwnBranch => 'Own Branch',
            self::OwnDepartment => 'Own Department',
            self::OwnTeam => 'Own Team',
            self::Assigned => 'Assigned Records',
            self::Own => 'Own Records',
            self::ReadOnly => 'Read Only',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $scope) => ['value' => $scope->value, 'label' => $scope->label()],
            self::cases(),
        );
    }
}

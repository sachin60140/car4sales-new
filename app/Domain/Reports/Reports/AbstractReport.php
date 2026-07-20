<?php

namespace App\Domain\Reports\Reports;

use App\Domain\Reports\Contracts\ReportDefinition;
use App\Domain\RolesPermissions\Services\ScopeService;
use Illuminate\Support\Carbon;

abstract class AbstractReport implements ReportDefinition
{
    public function __construct(protected readonly ScopeService $scopes) {}

    public function description(): string
    {
        return '';
    }

    /**
     * The standard date-range + branch filter set most reports share.
     *
     * @return array<int, array{key: string, label: string, type: string}>
     */
    public function filters(): array
    {
        return [
            ['key' => 'date_from', 'label' => 'From', 'type' => 'date'],
            ['key' => 'date_to', 'label' => 'To', 'type' => 'date'],
            ['key' => 'branch_id', 'label' => 'Branch', 'type' => 'branch'],
        ];
    }

    /**
     * Resolve the [from, to] range, defaulting to the last 30 days.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function range(array $filters): array
    {
        $to = ! empty($filters['date_to'])
            ? Carbon::parse((string) $filters['date_to'])->endOfDay()
            : now()->endOfDay();

        $from = ! empty($filters['date_from'])
            ? Carbon::parse((string) $filters['date_from'])->startOfDay()
            : now()->subDays(29)->startOfDay();

        return [$from, $to];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function branchId(array $filters): ?int
    {
        return ! empty($filters['branch_id']) ? (int) $filters['branch_id'] : null;
    }
}

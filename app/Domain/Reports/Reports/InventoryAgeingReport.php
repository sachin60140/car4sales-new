<?php

namespace App\Domain\Reports\Reports;

use App\Domain\Inventory\Models\Vehicle;
use App\Domain\Reports\Support\ReportResult;
use App\Models\User;

class InventoryAgeingReport extends AbstractReport
{
    private const IN_STOCK = [
        'in_stock', 'inspection_pending', 'under_refurbishment', 'ready_for_sale', 'published',
    ];

    public function key(): string
    {
        return 'inventory-ageing';
    }

    public function label(): string
    {
        return 'Inventory Ageing';
    }

    public function description(): string
    {
        return 'Current stock bucketed by days in inventory, with holding value.';
    }

    public function group(): string
    {
        return 'Inventory';
    }

    public function permission(): string
    {
        return 'vehicles.view';
    }

    /** Ageing is a snapshot — only the branch filter applies. */
    public function filters(): array
    {
        return [
            ['key' => 'branch_id', 'label' => 'Branch', 'type' => 'branch'],
        ];
    }

    public function run(array $filters, User $user): ReportResult
    {
        $branchId = $this->branchId($filters);

        $vehicles = Vehicle::query()
            ->whereIn('status', self::IN_STOCK)
            ->tap(fn ($q) => $this->scopes->apply($q, $user, ['branch' => 'branch_id', 'owner' => 'created_by']))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get(['id', 'created_at', 'landed_cost', 'status']);

        $buckets = [
            '0–30 days' => [0, 30],
            '31–60 days' => [31, 60],
            '61–90 days' => [61, 90],
            '90+ days' => [91, PHP_INT_MAX],
        ];

        $rows = [];
        $labels = [];
        $values = [];
        foreach ($buckets as $label => [$min, $max]) {
            $inBucket = $vehicles->filter(function (Vehicle $v) use ($min, $max) {
                $age = (int) $v->created_at->diffInDays(now());

                return $age >= $min && $age <= $max;
            });

            $rows[] = [
                'bucket' => $label,
                'count' => $inBucket->count(),
                'value' => (float) $inBucket->sum('landed_cost'),
            ];
            $labels[] = $label;
            $values[] = $inBucket->count();
        }

        $avgAge = $vehicles->count() > 0
            ? round($vehicles->avg(fn (Vehicle $v) => (int) $v->created_at->diffInDays(now())), 1)
            : 0.0;

        return new ReportResult(
            columns: [
                ['key' => 'bucket', 'label' => 'Age Bucket'],
                ['key' => 'count', 'label' => 'Vehicles', 'align' => 'right'],
                ['key' => 'value', 'label' => 'Holding Value', 'align' => 'right', 'format' => 'money'],
            ],
            rows: $rows,
            summary: [
                ['label' => 'Vehicles in stock', 'value' => $vehicles->count()],
                ['label' => 'Total holding value', 'value' => (float) $vehicles->sum('landed_cost'), 'format' => 'money'],
                ['label' => 'Average age', 'value' => $avgAge, 'format' => 'days'],
            ],
            chart: ['type' => 'bar', 'labels' => $labels, 'values' => $values],
        );
    }
}

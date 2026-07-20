<?php

namespace App\Domain\Reports\Reports;

use App\Domain\Deliveries\Enums\DeliveryStatus;
use App\Domain\Deliveries\Models\Delivery;
use App\Domain\Reports\Support\ReportResult;
use App\Domain\RTO\Enums\RtoStatus;
use App\Domain\RTO\Models\RtoCase;
use App\Models\User;

class DeliveryRtoReport extends AbstractReport
{
    public function key(): string
    {
        return 'delivery-rto';
    }

    public function label(): string
    {
        return 'Delivery & RTO';
    }

    public function description(): string
    {
        return 'Deliveries handed over in the period and the state of RTO transfers.';
    }

    public function group(): string
    {
        return 'Delivery';
    }

    public function permission(): string
    {
        return 'deliveries.view';
    }

    public function run(array $filters, User $user): ReportResult
    {
        [$from, $to] = $this->range($filters);
        $branchId = $this->branchId($filters);

        $deliveries = Delivery::query()
            ->with(['customer:id,name', 'vehicle:id,make,model'])
            ->whereBetween('created_at', [$from, $to])
            ->tap(fn ($q) => $this->scopes->apply($q, $user, ['branch' => 'branch_id', 'owner' => 'created_by']))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->latest()
            ->get();

        $rows = $deliveries->map(fn (Delivery $d) => [
            'delivery_number' => $d->delivery_number,
            'customer' => $d->customer?->name ?? '—',
            'vehicle' => trim(($d->vehicle?->make ?? '').' '.($d->vehicle?->model ?? '')) ?: '—',
            'status' => $d->status->label(),
            'delivered_at' => $d->delivered_at?->toDateString() ?? '—',
        ])->all();

        // RTO snapshot alongside the delivery period.
        $rtoOpen = RtoCase::query()
            ->tap(fn ($q) => $this->scopes->apply($q, $user, ['branch' => 'branch_id', 'assigned' => 'assigned_to', 'owner' => 'created_by']))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', '!=', RtoStatus::Closed->value)
            ->count();

        $byStatus = [];
        foreach (DeliveryStatus::cases() as $status) {
            $count = $deliveries->where('status', $status)->count();
            if ($count > 0) {
                $byStatus[$status->label()] = $count;
            }
        }

        return new ReportResult(
            columns: [
                ['key' => 'delivery_number', 'label' => 'Delivery #'],
                ['key' => 'customer', 'label' => 'Customer'],
                ['key' => 'vehicle', 'label' => 'Vehicle'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'delivered_at', 'label' => 'Delivered On'],
            ],
            rows: $rows,
            summary: [
                ['label' => 'Deliveries opened', 'value' => $deliveries->count()],
                ['label' => 'Approved', 'value' => $deliveries->where('status', DeliveryStatus::Approved)->count()],
                ['label' => 'Delivered', 'value' => $deliveries->where('status', DeliveryStatus::Delivered)->count()],
                ['label' => 'RTO cases open', 'value' => $rtoOpen],
            ],
            chart: ['type' => 'bar', 'labels' => array_keys($byStatus), 'values' => array_values($byStatus)],
        );
    }
}

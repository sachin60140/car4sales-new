<?php

namespace App\Domain\Reports\Reports;

use App\Domain\PurchaseLeads\Enums\PurchaseLeadStatus;
use App\Domain\PurchaseLeads\Models\PurchaseLead;
use App\Domain\Reports\Support\ReportResult;
use App\Models\User;

class PurchaseFunnelReport extends AbstractReport
{
    public function key(): string
    {
        return 'purchase-funnel';
    }

    public function label(): string
    {
        return 'Purchase Funnel';
    }

    public function description(): string
    {
        return 'Purchase leads by pipeline stage with conversion.';
    }

    public function group(): string
    {
        return 'Purchase';
    }

    public function permission(): string
    {
        return 'purchase-leads.view';
    }

    public function run(array $filters, User $user): ReportResult
    {
        [$from, $to] = $this->range($filters);
        $branchId = $this->branchId($filters);

        $counts = PurchaseLead::query()
            ->whereBetween('created_at', [$from, $to])
            ->tap(fn ($q) => $this->scopes->apply($q, $user, ['branch' => 'branch_id', 'assigned' => 'assigned_to', 'owner' => 'created_by']))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $rows = [];
        $labels = [];
        $values = [];
        $total = 0;
        foreach (PurchaseLeadStatus::cases() as $status) {
            $count = (int) ($counts[$status->value] ?? 0);
            $total += $count;
            if ($count > 0) {
                $rows[] = ['stage' => $status->label(), 'count' => $count];
                $labels[] = $status->label();
                $values[] = $count;
            }
        }

        $purchased = (int) ($counts[PurchaseLeadStatus::Purchased->value] ?? 0);
        $conversion = $total > 0 ? round($purchased / $total * 100, 1) : 0.0;

        return new ReportResult(
            columns: [
                ['key' => 'stage', 'label' => 'Stage'],
                ['key' => 'count', 'label' => 'Leads', 'align' => 'right'],
            ],
            rows: $rows,
            summary: [
                ['label' => 'Total leads', 'value' => $total],
                ['label' => 'Purchased', 'value' => $purchased],
                ['label' => 'Conversion', 'value' => $conversion, 'format' => 'percent'],
            ],
            chart: ['type' => 'bar', 'labels' => $labels, 'values' => $values],
        );
    }
}

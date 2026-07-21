<?php

namespace App\Domain\Reports\Reports;

use App\Domain\Bookings\Models\BookingPayment;
use App\Domain\Reports\Support\ReportResult;
use App\Models\User;
use Illuminate\Support\Carbon;

class CollectionsReport extends AbstractReport
{
    public function key(): string
    {
        return 'collections';
    }

    public function label(): string
    {
        return 'Collections';
    }

    public function description(): string
    {
        return 'Payments received in the period, totalled by day.';
    }

    public function group(): string
    {
        return 'Finance';
    }

    public function permission(): string
    {
        return 'payments.view';
    }

    public function run(array $filters, User $user): ReportResult
    {
        [$from, $to] = $this->range($filters);
        $branchId = $this->branchId($filters);

        $payments = BookingPayment::query()
            ->where('status', 'received')
            ->whereBetween('created_at', [$from, $to])
            ->whereHas('booking', function ($b) use ($user, $branchId) {
                $this->scopes->apply($b, $user, ['branch' => 'branch_id', 'assigned' => 'sales_executive_id', 'owner' => 'created_by']);
                if ($branchId) {
                    $b->where('branch_id', $branchId);
                }
            })
            ->get(['id', 'amount', 'created_at']);

        // Group by day. Amounts can be negative (reversals) — net them.
        $byDay = $payments->groupBy(fn (BookingPayment $p) => $p->created_at->toDateString())
            ->map(fn ($group) => [
                'count' => $group->count(),
                'amount' => (float) $group->sum('amount'),
            ])
            ->sortKeys();

        $rows = [];
        $labels = [];
        $values = [];
        foreach ($byDay as $date => $agg) {
            $rows[] = ['date' => $date, 'count' => $agg['count'], 'amount' => $agg['amount']];
            $labels[] = Carbon::parse($date)->format('d M');
            $values[] = $agg['amount'];
        }

        $total = (float) $payments->sum('amount');

        return new ReportResult(
            columns: [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'count', 'label' => 'Receipts', 'align' => 'right'],
                ['key' => 'amount', 'label' => 'Net Collected', 'align' => 'right', 'format' => 'money'],
            ],
            rows: $rows,
            summary: [
                ['label' => 'Total collected', 'value' => $total, 'format' => 'money'],
                ['label' => 'Receipts', 'value' => $payments->count()],
                ['label' => 'Active days', 'value' => $byDay->count()],
            ],
            chart: ['type' => 'bar', 'labels' => $labels, 'values' => $values],
        );
    }
}

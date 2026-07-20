<?php

namespace App\Domain\Reports\Reports;

use App\Domain\Finance\Enums\FinanceStatus;
use App\Domain\Finance\Models\FinanceApplication;
use App\Domain\Reports\Support\ReportResult;
use App\Models\User;

class FinanceDisbursementReport extends AbstractReport
{
    public function key(): string
    {
        return 'finance-disbursement';
    }

    public function label(): string
    {
        return 'Finance & Disbursement';
    }

    public function description(): string
    {
        return 'Finance files raised in the period with sanction and disbursement totals.';
    }

    public function group(): string
    {
        return 'Finance';
    }

    public function permission(): string
    {
        return 'finance.view';
    }

    public function run(array $filters, User $user): ReportResult
    {
        [$from, $to] = $this->range($filters);
        $branchId = $this->branchId($filters);

        $apps = FinanceApplication::query()
            ->with(['customer:id,name', 'lender:id,name'])
            ->whereBetween('created_at', [$from, $to])
            ->tap(fn ($q) => $this->scopes->apply($q, $user, ['branch' => 'branch_id', 'assigned' => 'assigned_to', 'owner' => 'created_by']))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->latest()
            ->get();

        $rows = $apps->map(fn (FinanceApplication $a) => [
            'application_number' => $a->application_number,
            'customer' => $a->customer?->name ?? '—',
            'lender' => $a->lender?->name ?? '—',
            'status' => $a->status->label(),
            'loan_amount' => (float) $a->loan_amount,
            'sanction_amount' => (float) $a->sanction_amount,
            'disbursed_amount' => (float) $a->disbursed_amount,
        ])->all();

        $byStatus = [];
        foreach (FinanceStatus::cases() as $status) {
            $count = $apps->where('status', $status)->count();
            if ($count > 0) {
                $byStatus[$status->label()] = $count;
            }
        }

        return new ReportResult(
            columns: [
                ['key' => 'application_number', 'label' => 'File #'],
                ['key' => 'customer', 'label' => 'Customer'],
                ['key' => 'lender', 'label' => 'Lender'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'loan_amount', 'label' => 'Loan', 'align' => 'right', 'format' => 'money'],
                ['key' => 'sanction_amount', 'label' => 'Sanctioned', 'align' => 'right', 'format' => 'money'],
                ['key' => 'disbursed_amount', 'label' => 'Disbursed', 'align' => 'right', 'format' => 'money'],
            ],
            rows: $rows,
            summary: [
                ['label' => 'Files', 'value' => $apps->count()],
                ['label' => 'Sanctioned', 'value' => (float) $apps->sum('sanction_amount'), 'format' => 'money'],
                ['label' => 'Disbursed', 'value' => (float) $apps->sum('disbursed_amount'), 'format' => 'money'],
                ['label' => 'Disbursed files', 'value' => $apps->where('status', FinanceStatus::Disbursed)->count()],
            ],
            chart: ['type' => 'bar', 'labels' => array_keys($byStatus), 'values' => array_values($byStatus)],
        );
    }
}

<?php

namespace App\Domain\Reports\Reports;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Models\Booking;
use App\Domain\Reports\Support\ReportResult;
use App\Models\User;

class SalesBookingsReport extends AbstractReport
{
    public function key(): string
    {
        return 'sales-bookings';
    }

    public function label(): string
    {
        return 'Sales & Bookings';
    }

    public function description(): string
    {
        return 'Bookings raised in the period with status, value and discounts.';
    }

    public function group(): string
    {
        return 'Sales';
    }

    public function permission(): string
    {
        return 'bookings.view';
    }

    public function run(array $filters, User $user): ReportResult
    {
        [$from, $to] = $this->range($filters);
        $branchId = $this->branchId($filters);

        $query = Booking::query()
            ->with(['customer:id,name', 'vehicle:id,stock_number,make,model'])
            ->whereBetween('created_at', [$from, $to])
            ->tap(fn ($q) => $this->scopes->apply($q, $user, ['branch' => 'branch_id', 'assigned' => 'sales_executive_id', 'owner' => 'created_by']))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->latest();

        $bookings = $query->get();

        $rows = $bookings->map(fn (Booking $b) => [
            'booking_number' => $b->booking_number,
            'customer' => $b->customer?->name ?? '—',
            'vehicle' => trim(($b->vehicle?->make ?? '').' '.($b->vehicle?->model ?? '')) ?: '—',
            'status' => $b->status->label(),
            'selling_price' => (float) $b->selling_price,
            'discount' => (float) $b->discount_amount,
            'net' => $b->netPayable(),
        ])->all();

        $byStatus = [];
        foreach (BookingStatus::cases() as $status) {
            $count = $bookings->where('status', $status)->count();
            if ($count > 0) {
                $byStatus[$status->label()] = $count;
            }
        }

        $summary = [
            ['label' => 'Total bookings', 'value' => $bookings->count()],
            ['label' => 'Confirmed', 'value' => $bookings->whereIn('status', [BookingStatus::Confirmed, BookingStatus::PaymentPending, BookingStatus::FinancePending, BookingStatus::ReadyForDelivery])->count()],
            ['label' => 'Delivered', 'value' => $bookings->where('status', BookingStatus::Delivered)->count()],
            ['label' => 'Gross value', 'value' => (float) $bookings->sum('selling_price'), 'format' => 'money'],
            ['label' => 'Total discount', 'value' => (float) $bookings->sum('discount_amount'), 'format' => 'money'],
        ];

        return new ReportResult(
            columns: [
                ['key' => 'booking_number', 'label' => 'Booking #'],
                ['key' => 'customer', 'label' => 'Customer'],
                ['key' => 'vehicle', 'label' => 'Vehicle'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'selling_price', 'label' => 'Selling Price', 'align' => 'right', 'format' => 'money'],
                ['key' => 'discount', 'label' => 'Discount', 'align' => 'right', 'format' => 'money'],
                ['key' => 'net', 'label' => 'Net Payable', 'align' => 'right', 'format' => 'money'],
            ],
            rows: $rows,
            summary: $summary,
            chart: [
                'type' => 'bar',
                'labels' => array_keys($byStatus),
                'values' => array_values($byStatus),
            ],
        );
    }
}

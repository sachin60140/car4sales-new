<?php

namespace App\Domain\Reports\Support;

use App\Domain\Reports\Contracts\ReportDefinition;
use App\Domain\Reports\Reports\CollectionsReport;
use App\Domain\Reports\Reports\DeliveryRtoReport;
use App\Domain\Reports\Reports\FinanceDisbursementReport;
use App\Domain\Reports\Reports\InventoryAgeingReport;
use App\Domain\Reports\Reports\PurchaseFunnelReport;
use App\Domain\Reports\Reports\SalesBookingsReport;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Registry of available reports. Resolves definitions from the container so each
 * gets its ScopeService dependency, and filters the catalogue by the viewer's
 * permissions.
 */
class ReportRegistry
{
    /** @var array<int, class-string<ReportDefinition>> */
    private const REPORTS = [
        SalesBookingsReport::class,
        PurchaseFunnelReport::class,
        InventoryAgeingReport::class,
        FinanceDisbursementReport::class,
        DeliveryRtoReport::class,
        CollectionsReport::class,
    ];

    /** @return Collection<string, ReportDefinition> keyed by report key */
    public function all(): Collection
    {
        return collect(self::REPORTS)
            ->map(fn (string $class) => app($class))
            ->keyBy(fn (ReportDefinition $report) => $report->key());
    }

    public function get(string $key): ?ReportDefinition
    {
        return $this->all()->get($key);
    }

    /**
     * Reports the user is permitted to run (Super Admin sees all).
     *
     * @return Collection<string, ReportDefinition>
     */
    public function forUser(User $user): Collection
    {
        return $this->all()->filter(
            fn (ReportDefinition $report) => $user->hasRole('Super Admin') || $user->can($report->permission()),
        );
    }
}

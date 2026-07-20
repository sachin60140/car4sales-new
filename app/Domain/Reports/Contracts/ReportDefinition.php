<?php

namespace App\Domain\Reports\Contracts;

use App\Domain\Reports\Support\ReportResult;
use App\Models\User;

/**
 * A single report. Definitions are stateless and registered in the
 * ReportRegistry; each declares the permission that gates it, the filters it
 * accepts and how to run against scoped data.
 */
interface ReportDefinition
{
    /** Stable url-safe key, e.g. "sales-bookings". */
    public function key(): string;

    public function label(): string;

    public function description(): string;

    /** Group heading for the report index, e.g. "Sales". */
    public function group(): string;

    /** Permission required to view/run this report. */
    public function permission(): string;

    /**
     * Filter inputs to render in the UI.
     *
     * @return array<int, array{key: string, label: string, type: string, options?: array<int, array{value: string, label: string}>}>
     */
    public function filters(): array;

    /**
     * Run the report against branch-scoped data for the given user.
     *
     * @param  array<string, mixed>  $filters
     */
    public function run(array $filters, User $user): ReportResult;
}

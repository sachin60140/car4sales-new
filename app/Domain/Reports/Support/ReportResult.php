<?php

namespace App\Domain\Reports\Support;

/**
 * The output of a report run: display columns, data rows, headline summary
 * figures and an optional chart series. Shared by the web view, CSV and PDF
 * exports so every surface renders the same numbers.
 */
class ReportResult
{
    /**
     * @param  array<int, array{key: string, label: string, align?: string, format?: string}>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array{label: string, value: mixed, format?: string}>  $summary
     * @param  array{type: string, labels: array<int, string>, values: array<int, float|int>}|null  $chart
     */
    public function __construct(
        public readonly array $columns,
        public readonly array $rows,
        public readonly array $summary = [],
        public readonly ?array $chart = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'columns' => $this->columns,
            'rows' => $this->rows,
            'summary' => $this->summary,
            'chart' => $this->chart,
        ];
    }
}

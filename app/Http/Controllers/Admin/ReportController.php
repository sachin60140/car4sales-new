<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Branches\Models\Branch;
use App\Domain\Reports\Contracts\ReportDefinition;
use App\Domain\Reports\Support\ReportRegistry;
use App\Domain\Reports\Support\ReportResult;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportRegistry $registry) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('reports.access-reports'), 403);

        $reports = $this->registry->forUser($request->user())
            ->values()
            ->map(fn (ReportDefinition $r) => [
                'key' => $r->key(),
                'label' => $r->label(),
                'description' => $r->description(),
                'group' => $r->group(),
            ]);

        return Inertia::render('admin/reports/Index', [
            'reports' => $reports,
        ]);
    }

    public function show(Request $request, string $report): Response
    {
        $definition = $this->resolve($request, $report);

        $filters = $this->filterValues($request);
        $result = $definition->run($filters, $request->user());

        return Inertia::render('admin/reports/Show', [
            'report' => [
                'key' => $definition->key(),
                'label' => $definition->label(),
                'description' => $definition->description(),
                'group' => $definition->group(),
                'filters' => $definition->filters(),
            ],
            'filters' => $filters,
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'result' => $result->toArray(),
            'can' => [
                'export' => $request->user()->can('reports.export'),
            ],
        ]);
    }

    public function export(Request $request, string $report): StreamedResponse|\Illuminate\Http\Response
    {
        abort_unless($request->user()->can('reports.export'), 403);

        $definition = $this->resolve($request, $report);
        $filters = $this->filterValues($request);
        $result = $definition->run($filters, $request->user());
        $format = $request->string('format', 'csv')->toString();
        $filename = $definition->key().'-'.now()->format('Y-m-d');

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.pdf', [
                'title' => $definition->label(),
                'generatedAt' => now(),
                'result' => $result,
                'range' => $filters,
            ])->setPaper('a4', 'landscape');

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
            ]);
        }

        return $this->streamCsv($result, $filename);
    }

    private function resolve(Request $request, string $reportKey): ReportDefinition
    {
        abort_unless($request->user()->can('reports.access-reports'), 403);

        $definition = $this->registry->get($reportKey);
        abort_if($definition === null, 404);
        abort_unless($request->user()->hasRole('Super Admin') || $request->user()->can($definition->permission()), 403);

        return $definition;
    }

    /**
     * @return array<string, mixed>
     */
    private function filterValues(Request $request): array
    {
        return [
            'date_from' => $request->string('date_from')->toString() ?: null,
            'date_to' => $request->string('date_to')->toString() ?: null,
            'branch_id' => $request->integer('branch_id') ?: null,
        ];
    }

    private function streamCsv(ReportResult $result, string $filename): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.csv"',
        ];

        return response()->streamDownload(function () use ($result) {
            $out = fopen('php://output', 'w');

            fputcsv($out, array_map(fn ($c) => $c['label'], $result->columns));

            foreach ($result->rows as $row) {
                fputcsv($out, array_map(fn ($c) => $row[$c['key']] ?? '', $result->columns));
            }

            // Blank line then the summary block.
            if ($result->summary !== []) {
                fputcsv($out, []);
                fputcsv($out, ['Summary']);
                foreach ($result->summary as $item) {
                    fputcsv($out, [$item['label'], $item['value']]);
                }
            }

            fclose($out);
        }, Str::slug($filename).'.csv', $headers);
    }
}

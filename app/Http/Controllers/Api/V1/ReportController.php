<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reports\Contracts\ReportDefinition;
use App\Domain\Reports\Support\ReportRegistry;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private readonly ReportRegistry $registry) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('reports.access-reports'), 403);

        $reports = $this->registry->forUser($request->user())
            ->values()
            ->map(fn (ReportDefinition $r) => [
                'key' => $r->key(),
                'label' => $r->label(),
                'group' => $r->group(),
                'filters' => $r->filters(),
            ]);

        return ApiResponse::success(['reports' => $reports]);
    }

    public function show(Request $request, string $report): JsonResponse
    {
        abort_unless($request->user()->can('reports.access-reports'), 403);

        $definition = $this->registry->get($report);
        abort_if($definition === null, 404);
        abort_unless($request->user()->hasRole('Super Admin') || $request->user()->can($definition->permission()), 403);

        $filters = [
            'date_from' => $request->string('date_from')->toString() ?: null,
            'date_to' => $request->string('date_to')->toString() ?: null,
            'branch_id' => $request->integer('branch_id') ?: null,
        ];

        $result = $definition->run($filters, $request->user());

        return ApiResponse::success([
            'report' => ['key' => $definition->key(), 'label' => $definition->label()],
            'filters' => $filters,
            'result' => $result->toArray(),
        ]);
    }
}

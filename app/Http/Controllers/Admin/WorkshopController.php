<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Inventory\Models\Vehicle;
use App\Domain\Refurbishment\Actions\WorkshopJobAction;
use App\Domain\Refurbishment\Enums\WorkshopJobStatus;
use App\Domain\Refurbishment\Models\WorkshopJob;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Domain\Vendors\Models\Vendor;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkshopController extends Controller
{
    public function __construct(
        private readonly ScopeService $scopes,
        private readonly WorkshopJobAction $jobs,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', WorkshopJob::class);

        $records = WorkshopJob::query()
            ->with(['vehicle:id,stock_number,make,model', 'vendor:id,name'])
            ->tap(fn ($q) => $this->scopes->apply($q, $request->user(), ['branch' => 'branch_id', 'owner' => 'created_by']))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (WorkshopJob $j) => [
                'id' => $j->id,
                'job_number' => $j->job_number,
                'vehicle' => $j->vehicle?->only(['id', 'stock_number', 'make', 'model']),
                'vendor' => $j->vendor?->only(['id', 'name']),
                'type' => $j->type,
                'status' => $j->status->value,
                'status_label' => $j->status->label(),
                'estimate_total' => $j->estimate_total,
                'approved_total' => $j->approved_total,
                'actual_total' => $j->actual_total,
                'expected_completion' => $j->expected_completion?->toDateString(),
            ]);

        return Inertia::render('admin/workshop/Index', [
            'jobs' => $records,
            'statuses' => WorkshopJobStatus::options(),
            'filters' => ['status' => $request->string('status')->toString() ?: null],
            'can' => ['create' => $request->user()->can('create', WorkshopJob::class)],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', WorkshopJob::class);

        $data = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'type' => ['required', 'string', 'in:internal,external'],
            'description' => ['nullable', 'string', 'max:2000'],
            'expected_completion' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.defect' => ['nullable', 'string', 'max:255'],
            'items.*.work_type' => ['required', 'string', 'in:part,labour'],
            'items.*.estimate' => ['required', 'numeric', 'min:0'],
        ]);

        $vehicle = Vehicle::query()->findOrFail($data['vehicle_id']);
        $job = $this->jobs->create($vehicle, $data, $data['items'], $request->user());

        return redirect()->route('admin.workshop.show', $job)->with('success', "Job {$job->job_number} created.");
    }

    public function show(Request $request, WorkshopJob $workshopJob): Response
    {
        $this->authorize('view', $workshopJob);

        $workshopJob->load(['vehicle:id,stock_number,make,model,variant,status', 'vendor:id,name', 'items', 'creator:id,name']);

        return Inertia::render('admin/workshop/Show', [
            'job' => $workshopJob,
            'can' => [
                'approve' => $request->user()->can('approve', $workshopJob),
                'update' => $request->user()->can('update', $workshopJob),
            ],
        ]);
    }

    public function approve(Request $request, WorkshopJob $workshopJob): RedirectResponse
    {
        $this->authorize('approve', $workshopJob);

        $data = $request->validate(['approved_total' => ['nullable', 'numeric', 'min:0']]);
        $this->jobs->approve($workshopJob, $request->user(), isset($data['approved_total']) ? (float) $data['approved_total'] : null);

        return back()->with('success', 'Job approved.');
    }

    public function start(Request $request, WorkshopJob $workshopJob): RedirectResponse
    {
        $this->authorize('update', $workshopJob);

        $this->jobs->start($workshopJob, $request->user());

        return back()->with('success', 'Job started.');
    }

    public function complete(Request $request, WorkshopJob $workshopJob): RedirectResponse
    {
        $this->authorize('update', $workshopJob);

        $data = $request->validate([
            'qc' => ['required', 'string', 'in:passed,failed'],
            'actual_total' => ['required', 'numeric', 'min:0'],
            'items' => ['array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.actual_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $this->jobs->complete($workshopJob, $request->user(), $data['items'] ?? [], $data['qc'], (float) $data['actual_total']);

        return back()->with('success', 'Job completed.');
    }
}

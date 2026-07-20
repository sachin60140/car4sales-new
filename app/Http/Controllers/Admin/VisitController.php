<?php

namespace App\Http\Controllers\Admin;

use App\Domain\RolesPermissions\Services\ScopeService;
use App\Domain\SalesLeads\Models\SalesLead;
use App\Domain\Visits\Actions\ScheduleVisitAction;
use App\Domain\Visits\Enums\VisitStatus;
use App\Domain\Visits\Models\CustomerVisit;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VisitController extends Controller
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('visits.view'), 403);

        $visits = CustomerVisit::query()
            ->with(['lead:id,lead_number', 'customer:id,name,mobile', 'branch:id,name', 'attendedBy:id,name'])
            ->tap(fn ($q) => $this->scopes->apply($q, $request->user(), ['branch' => 'branch_id', 'assigned' => 'attended_by', 'owner' => 'created_by']))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->latest('scheduled_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (CustomerVisit $v) => [
                'id' => $v->id,
                'visit_number' => $v->visit_number,
                'customer' => $v->customer?->only(['id', 'name', 'mobile']),
                'lead' => $v->lead?->only(['id', 'lead_number']),
                'branch' => $v->branch?->only(['id', 'name']),
                'attended_by' => $v->attendedBy?->only(['id', 'name']),
                'scheduled_at' => $v->scheduled_at?->toDateTimeString(),
                'status' => $v->status->value,
                'status_label' => $v->status->label(),
                'outcome' => $v->outcome,
            ]);

        return Inertia::render('admin/visits/Index', [
            'visits' => $visits,
            'statuses' => VisitStatus::options(),
            'filters' => ['status' => $request->string('status')->toString() ?: null],
        ]);
    }

    public function store(Request $request, SalesLead $salesLead, ScheduleVisitAction $action): RedirectResponse
    {
        abort_unless($request->user()->can('visits.create') && $request->user()->can('update', $salesLead), 403);

        $data = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'attended_by' => ['nullable', 'integer', 'exists:users,id'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $action->schedule($salesLead, $data, $request->user());

        return back()->with('success', 'Visit scheduled.');
    }

    public function complete(Request $request, CustomerVisit $visit, ScheduleVisitAction $action): RedirectResponse
    {
        abort_unless($request->user()->can('visits.update'), 403);

        $data = $request->validate([
            'outcome' => ['nullable', 'string', 'max:255'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $action->complete($visit, $data, $request->user());

        return back()->with('success', 'Visit marked completed.');
    }
}

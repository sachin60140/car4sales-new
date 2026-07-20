<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Branches\Models\Branch;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Domain\SalesLeads\Actions\AssignLeadAction;
use App\Domain\SalesLeads\Actions\CreateSalesLeadAction;
use App\Domain\SalesLeads\Actions\LogCallAction;
use App\Domain\SalesLeads\Enums\CallOutcome;
use App\Domain\SalesLeads\Enums\SalesLeadSource;
use App\Domain\SalesLeads\Enums\SalesLeadStatus;
use App\Domain\SalesLeads\Models\LeadLostReason;
use App\Domain\SalesLeads\Models\SalesLead;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Workflow\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SalesLeadController extends Controller
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SalesLead::class);

        $user = $request->user();

        $leads = SalesLead::query()
            ->with(['branch:id,name', 'telecaller:id,name', 'salesExecutive:id,name', 'interestedVehicle:id,stock_number,make,model'])
            ->tap(fn ($q) => $this->scopes->apply($q, $user, ['branch' => 'branch_id', 'assigned' => 'telecaller_id', 'owner' => 'created_by']))
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")->orWhere('mobile', 'like', "%{$s}%")->orWhere('lead_number', 'like', "%{$s}%")))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('priority')->toString(), fn ($q, $p) => $q->where('priority', $p))
            ->when($request->integer('branch_id'), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->integer('telecaller_id'), fn ($q, $id) => $q->where('telecaller_id', $id))
            ->when($request->string('queue')->toString() === 'my', fn ($q) => $q->where(fn ($w) => $w->where('telecaller_id', $user->id)->orWhere('sales_executive_id', $user->id)))
            ->when($request->string('queue')->toString() === 'due', fn ($q) => $q
                ->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now()->endOfDay())
                ->whereIn('status', SalesLeadStatus::openValues()))
            ->orderByRaw("CASE priority WHEN 'hot' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderBy('next_follow_up_at')
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (SalesLead $l) => [
                'id' => $l->id,
                'lead_number' => $l->lead_number,
                'name' => $l->name,
                'mobile' => $l->mobile,
                'city' => $l->city,
                'status' => $l->status->value,
                'status_label' => $l->status->label(),
                'priority' => $l->priority,
                'source' => $l->source,
                'branch' => $l->branch?->only(['id', 'name']),
                'telecaller' => $l->telecaller?->only(['id', 'name']),
                'sales_executive' => $l->salesExecutive?->only(['id', 'name']),
                'interested_vehicle' => $l->interestedVehicle?->only(['id', 'stock_number', 'make', 'model']),
                'next_follow_up_at' => $l->next_follow_up_at?->toDateTimeString(),
                'overdue' => $l->next_follow_up_at !== null && $l->next_follow_up_at->isPast(),
            ]);

        return Inertia::render('admin/sales-leads/Index', [
            'leads' => $leads,
            'statuses' => SalesLeadStatus::options(),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'telecallers' => $this->assignableUsers('Telecaller'),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString() ?: null,
                'priority' => $request->string('priority')->toString() ?: null,
                'branch_id' => $request->integer('branch_id') ?: null,
                'telecaller_id' => $request->integer('telecaller_id') ?: null,
                'queue' => $request->string('queue')->toString() ?: null,
            ],
            'can' => [
                'create' => $user->can('create', SalesLead::class),
                'assign' => $user->can('sales-leads.assign'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', SalesLead::class);

        return Inertia::render('admin/sales-leads/Create', [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'sources' => SalesLeadSource::options(),
            'telecallers' => $this->assignableUsers('Telecaller'),
        ]);
    }

    public function store(Request $request, CreateSalesLeadAction $action): RedirectResponse
    {
        $this->authorize('create', SalesLead::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'budget_min' => ['nullable', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'numeric', 'min:0'],
            'source' => ['required', Rule::enum(SalesLeadSource::class)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'telecaller_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'finance_required' => ['boolean'],
            'exchange_required' => ['boolean'],
            'priority' => ['required', 'string', 'in:low,normal,high,hot'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $lead = $action->execute($data, $request->user());

        return redirect()->route('admin.sales-leads.show', $lead)->with('success', "Lead {$lead->lead_number} created.");
    }

    public function show(Request $request, SalesLead $salesLead): Response
    {
        $this->authorize('view', $salesLead);

        $salesLead->load([
            'customer', 'branch:id,name', 'telecaller:id,name', 'salesExecutive:id,name',
            'interestedVehicle:id,stock_number,make,model,slug', 'lostReason:id,label',
            'followups.user:id,name', 'activities.user:id,name', 'statusHistories.changer:id,name',
            'assignments.toUser:id,name',
            'visits:id,sales_lead_id,visit_number,scheduled_at,status,outcome',
            'testDrives:id,sales_lead_id,td_number,vehicle_id,scheduled_at,status',
            'testDrives.vehicle:id,stock_number,make,model',
            'bookings:id,sales_lead_id,booking_number,status,selling_price',
        ]);

        // Sibling leads for the same customer (their history).
        $customerHistory = $salesLead->customer
            ? SalesLead::query()->where('customer_id', $salesLead->customer_id)
                ->where('id', '!=', $salesLead->id)->latest()->limit(10)
                ->get(['id', 'lead_number', 'status', 'created_at'])
            : collect();

        return Inertia::render('admin/sales-leads/Show', [
            'lead' => $salesLead,
            'customerHistory' => $customerHistory,
            'callOutcomes' => CallOutcome::options(),
            'lostReasons' => LeadLostReason::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'label']),
            'allowedTransitions' => array_map(
                fn (SalesLeadStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                $salesLead->status->allowedTransitions(),
            ),
            'telecallers' => $this->assignableUsers('Telecaller'),
            'salesExecutives' => $this->assignableUsers('Sales Executive'),
            'availableVehicles' => \App\Domain\Inventory\Models\Vehicle::query()
                ->whereIn('status', ['ready_for_sale', 'published', 'reserved'])
                ->orderBy('stock_number')
                ->limit(200)
                ->get(['id', 'stock_number', 'make', 'model', 'asking_price', 'status']),
            'can' => [
                'update' => $request->user()->can('update', $salesLead),
                'assign' => $request->user()->can('assign', $salesLead),
                'scheduleVisit' => $request->user()->can('visits.create'),
                'scheduleTestDrive' => $request->user()->can('test-drives.create'),
                'createBooking' => $request->user()->can('bookings.create'),
            ],
        ]);
    }

    public function logCall(Request $request, SalesLead $salesLead, LogCallAction $action): RedirectResponse
    {
        $this->authorize('update', $salesLead);

        $data = $request->validate([
            'outcome' => ['required', Rule::enum(CallOutcome::class)],
            'channel' => ['nullable', 'string', 'in:call,whatsapp,sms,email,visit'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'next_follow_up_at' => ['nullable', 'date'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'lost_reason_id' => ['nullable', 'integer', Rule::exists('lead_lost_reasons', 'id')],
        ]);

        $action->execute($salesLead, CallOutcome::from($data['outcome']), $data, $request->user());

        return back()->with('success', 'Call logged.');
    }

    public function transition(Request $request, SalesLead $salesLead, WorkflowService $workflow): RedirectResponse
    {
        $this->authorize('update', $salesLead);

        $data = $request->validate([
            'status' => ['required', Rule::enum(SalesLeadStatus::class)],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'lost_reason_id' => ['nullable', 'integer', Rule::exists('lead_lost_reasons', 'id')],
        ]);

        $target = SalesLeadStatus::from($data['status']);

        if ($target->isLost() && empty($data['lost_reason_id'])) {
            throw ValidationException::withMessages(['lost_reason_id' => 'A reason is required when marking the lead as lost.']);
        }

        if ($target->isLost()) {
            $salesLead->update(['lost_reason_id' => $data['lost_reason_id']]);
        }

        $workflow->transition($salesLead, $target, $request->user(), $data['remarks'] ?? null);

        return back()->with('success', 'Lead status updated.');
    }

    public function assign(Request $request, SalesLead $salesLead, AssignLeadAction $action): RedirectResponse
    {
        $this->authorize('assign', $salesLead);

        $data = $request->validate([
            'role' => ['required', 'string', 'in:telecaller,sales_executive'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $action->execute($salesLead, $data['role'], $data['user_id'] ?? null, $request->user(), $data['reason'] ?? null);

        return back()->with('success', 'Lead reassigned.');
    }

    /** @return \Illuminate\Support\Collection<int, array{id: int, name: string}> */
    private function assignableUsers(string $role)
    {
        return User::query()->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', $role))
            ->orderBy('name')->get(['id', 'name']);
    }
}

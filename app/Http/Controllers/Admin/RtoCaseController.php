<?php

namespace App\Http\Controllers\Admin;

use App\Domain\RolesPermissions\Services\ScopeService;
use App\Domain\RTO\Actions\RtoCaseAction;
use App\Domain\RTO\Enums\RtoStatus;
use App\Domain\RTO\Models\RtoCase;
use App\Domain\RTO\Models\RtoHold;
use App\Domain\Vendors\Models\Vendor;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RtoCaseController extends Controller
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', RtoCase::class);

        $cases = RtoCase::query()
            ->with(['vehicle:id,stock_number,make,model,registration_number', 'buyer:id,name,mobile', 'assignee:id,name', 'branch:id,name'])
            ->tap(fn ($q) => $this->scopes->apply($q, $request->user(), ['branch' => 'branch_id', 'assigned' => 'assigned_to', 'owner' => 'created_by']))
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('rto_number', 'like', "%{$s}%")
                ->orWhere('application_number', 'like', "%{$s}%")
                ->orWhereHas('vehicle', fn ($v) => $v->where('registration_number', 'like', "%{$s}%")->orWhere('stock_number', 'like', "%{$s}%"))))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->boolean('mine'), fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (RtoCase $c) => [
                'id' => $c->id,
                'rto_number' => $c->rto_number,
                'vehicle' => $c->vehicle === null ? null : [
                    'id' => $c->vehicle->id,
                    'stock_number' => $c->vehicle->stock_number,
                    'registration_number' => $c->vehicle->registration_number,
                    'title' => trim("{$c->vehicle->make} {$c->vehicle->model}"),
                ],
                'buyer' => $c->buyer?->only(['id', 'name', 'mobile']),
                'assignee' => $c->assignee?->only(['id', 'name']),
                'branch' => $c->branch?->only(['id', 'name']),
                'status' => $c->status->value,
                'status_label' => $c->status->label(),
                'expected_completion' => $c->expected_completion?->toDateString(),
                'hold_amount' => $c->hold_amount,
            ]);

        return Inertia::render('admin/rto/Index', [
            'cases' => $cases,
            'statuses' => RtoStatus::options(),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString() ?: null,
                'mine' => $request->boolean('mine'),
            ],
        ]);
    }

    public function show(Request $request, RtoCase $rtoCase): Response
    {
        $this->authorize('view', $rtoCase);

        $rtoCase->load([
            'vehicle:id,stock_number,make,model,variant,registration_number,registration_state',
            'buyer:id,name,mobile', 'seller:id,name,mobile', 'assignee:id,name', 'agent:id,name',
            'branch:id,name', 'delivery:id,delivery_number', 'booking:id,booking_number',
            'statusHistories.changedBy:id,name', 'documents.uploader:id,name',
            'movements.mover:id,name', 'expenses.recordedBy:id,name', 'holds.heldBy:id,name', 'holds.releasedBy:id,name',
        ]);

        return Inertia::render('admin/rto/Show', [
            'rtoCase' => [
                ...$rtoCase->only([
                    'id', 'rto_number', 'status', 'from_rto', 'to_rto',
                    'application_number', 'hold_amount', 'rc_copy_path', 'remarks',
                ]),
                'sale_date' => $rtoCase->sale_date?->toDateString(),
                'delivery_date' => $rtoCase->delivery_date?->toDateString(),
                'expected_completion' => $rtoCase->expected_completion?->toDateString(),
                'status_label' => $rtoCase->status->label(),
                'vehicle' => $rtoCase->vehicle,
                'buyer' => $rtoCase->buyer,
                'seller' => $rtoCase->seller,
                'assignee' => $rtoCase->assignee?->only(['id', 'name']),
                'agent' => $rtoCase->agent?->only(['id', 'name']),
                'branch' => $rtoCase->branch?->only(['id', 'name']),
                'delivery' => $rtoCase->delivery?->only(['id', 'delivery_number']),
                'booking' => $rtoCase->booking?->only(['id', 'booking_number']),
                'total_expenses' => $rtoCase->totalExpenses(),
                'documents' => $rtoCase->documents->map(fn ($d) => [
                    'id' => $d->id, 'type' => $d->type, 'file_path' => $d->file_path,
                    'status' => $d->status, 'uploader' => $d->uploader?->only(['id', 'name']),
                ]),
                'movements' => $rtoCase->movements->map(fn ($m) => [
                    'id' => $m->id, 'document' => $m->document, 'from_holder' => $m->from_holder,
                    'to_holder' => $m->to_holder, 'mover' => $m->mover?->only(['id', 'name']),
                    'moved_at' => $m->moved_at?->toDateTimeString(), 'remarks' => $m->remarks,
                ]),
                'expenses' => $rtoCase->expenses->map(fn ($e) => [
                    'id' => $e->id, 'head' => $e->head, 'amount' => $e->amount,
                    'reference' => $e->reference, 'recorded_by' => $e->recordedBy?->only(['id', 'name']),
                    'created_at' => $e->created_at?->toDateTimeString(),
                ]),
                'holds' => $rtoCase->holds->map(fn ($h) => [
                    'id' => $h->id, 'amount' => $h->amount, 'reason' => $h->reason, 'status' => $h->status,
                    'held_by' => $h->heldBy?->only(['id', 'name']), 'released_by' => $h->releasedBy?->only(['id', 'name']),
                    'released_at' => $h->released_at?->toDateTimeString(),
                ]),
                'histories' => $rtoCase->statusHistories->map(fn ($s) => [
                    'id' => $s->id, 'from_status' => $s->from_status, 'to_status' => $s->to_status,
                    'changed_by' => $s->changedBy?->only(['id', 'name']), 'remarks' => $s->remarks,
                    'created_at' => $s->created_at?->toDateTimeString(),
                ]),
            ],
            'allowedTransitions' => array_map(
                fn (RtoStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                $rtoCase->status->allowedTransitions(),
            ),
            'agents' => Vendor::query()->where('is_active', true)->where('type', 'rto_agent')->orderBy('name')->get(['id', 'name']),
            'executives' => User::query()->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('name', 'RTO Executive'))
                ->orderBy('name')->get(['id', 'name']),
            'can' => [
                'update' => $request->user()->can('update', $rtoCase),
                'assign' => $request->user()->can('assign', $rtoCase),
            ],
        ]);
    }

    public function transition(Request $request, RtoCase $rtoCase, RtoCaseAction $action): RedirectResponse
    {
        $this->authorize('update', $rtoCase);

        $data = $request->validate([
            'status' => ['required', Rule::enum(RtoStatus::class)],
            'application_number' => ['nullable', 'string', 'max:255'],
            'expected_completion' => ['nullable', 'date'],
            'to_rto' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        // Persist any live field edits alongside the transition.
        $rtoCase->fill(array_filter([
            'application_number' => $data['application_number'] ?? null,
            'expected_completion' => $data['expected_completion'] ?? null,
            'to_rto' => $data['to_rto'] ?? null,
        ], fn ($v) => $v !== null))->save();

        $action->transition($rtoCase, RtoStatus::from($data['status']), $request->user(), $data['remarks'] ?? null);

        return back()->with('success', 'RTO status updated.');
    }

    public function assign(Request $request, RtoCase $rtoCase, RtoCaseAction $action): RedirectResponse
    {
        $this->authorize('assign', $rtoCase);

        $data = $request->validate([
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'agent_vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
        ]);

        $action->assign($rtoCase, $data['assigned_to'] ?? null, $data['agent_vendor_id'] ?? null, $request->user());

        return back()->with('success', 'RTO case assignment updated.');
    }

    public function recordMovement(Request $request, RtoCase $rtoCase, RtoCaseAction $action): RedirectResponse
    {
        $this->authorize('update', $rtoCase);

        $data = $request->validate([
            'document' => ['required', 'string', 'max:255'],
            'to_holder' => ['required', 'string', 'max:255'],
            'from_holder' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $action->recordMovement($rtoCase, $data['document'], $data['to_holder'], $request->user(), $data['from_holder'] ?? null, $data['remarks'] ?? null);

        return back()->with('success', 'Document movement recorded.');
    }

    public function addExpense(Request $request, RtoCase $rtoCase, RtoCaseAction $action): RedirectResponse
    {
        $this->authorize('update', $rtoCase);

        $data = $request->validate([
            'head' => ['required', 'string', 'max:40'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $action->addExpense($rtoCase, $data['head'], (float) $data['amount'], $request->user(), $data['reference'] ?? null);

        return back()->with('success', 'RTO expense recorded.');
    }

    public function addHold(Request $request, RtoCase $rtoCase, RtoCaseAction $action): RedirectResponse
    {
        $this->authorize('update', $rtoCase);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $action->addHold($rtoCase, (float) $data['amount'], $data['reason'], $request->user());

        return back()->with('success', 'Hold placed on the deal.');
    }

    public function releaseHold(Request $request, RtoHold $hold, RtoCaseAction $action): RedirectResponse
    {
        $this->authorize('update', $hold->rtoCase);

        $action->releaseHold($hold, $request->user());

        return back()->with('success', 'Hold released.');
    }

    public function uploadRc(Request $request, RtoCase $rtoCase): RedirectResponse
    {
        $this->authorize('update', $rtoCase);

        $request->validate([
            'rc_copy' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $path = $request->file('rc_copy')->store("rto/{$rtoCase->id}", 'private');
        $rtoCase->update(['rc_copy_path' => $path]);

        return back()->with('success', 'RC copy uploaded.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Branches\Models\Branch;
use App\Domain\PurchaseLeads\Actions\CreatePurchaseLeadAction;
use App\Domain\PurchaseLeads\Enums\PurchaseLeadStatus;
use App\Domain\PurchaseLeads\Models\PurchaseLead;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\PurchaseLeadRequest;
use App\Models\User;
use App\Support\Workflow\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseLeadController extends Controller
{
    public function __construct(
        private readonly ScopeService $scopes,
        private readonly WorkflowService $workflow,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PurchaseLead::class);

        $leads = PurchaseLead::query()
            ->with(['assignee:id,name', 'branch:id,name'])
            ->tap(fn ($q) => $this->scopes->apply($q, $request->user(), [
                'branch' => 'branch_id', 'assigned' => 'assigned_to', 'owner' => 'created_by',
            ]))
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('lead_number', 'like', "%{$s}%")
                ->orWhere('seller_name', 'like', "%{$s}%")
                ->orWhere('mobile', 'like', "%{$s}%")
                ->orWhere('registration_number', 'like', "%{$s}%")))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->integer('branch_id'), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->string('priority')->toString(), fn ($q, $p) => $q->where('priority', $p))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/purchase/leads/Index', [
            'leads' => $leads,
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => PurchaseLeadStatus::options(),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString() ?: null,
                'branch_id' => $request->integer('branch_id') ?: null,
                'priority' => $request->string('priority')->toString() ?: null,
            ],
            'can' => [
                'create' => $request->user()->can('create', PurchaseLead::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', PurchaseLead::class);

        return Inertia::render('admin/purchase/leads/Create', $this->formProps());
    }

    public function store(PurchaseLeadRequest $request, CreatePurchaseLeadAction $action): RedirectResponse
    {
        $data = $request->validated();
        $data['branch_id'] ??= $request->user()->branch_id;

        $lead = $action->execute($data, $request->user());

        return redirect()
            ->route('admin.purchase-leads.show', $lead)
            ->with('success', "Purchase lead {$lead->lead_number} created.");
    }

    public function show(Request $request, PurchaseLead $purchaseLead): Response
    {
        $this->authorize('view', $purchaseLead);

        $canViewKyc = $request->user()->can('sellers.view-kyc');
        $canViewCost = $request->user()->can('valuations.view-purchase-cost');

        $purchaseLead->load([
            'assignee:id,name', 'branch:id,name', 'seller',
            'followups.user:id,name',
            'statusHistories.changer:id,name',
            'verifications',
            'inspections' => fn ($q) => $q->latest(),
            'inspections.inspector:id,name',
            'valuation.preparedBy:id,name',
            'purchase.approvalRequest.steps.role:id,name',
            'purchase.payments',
            'purchase.possession',
            'documents' => fn ($q) => $q->latest(),
        ]);

        // The purchase-approval request is attached to the lead itself (the
        // VehiclePurchase only exists once approved), so surface the latest one
        // directly — the Approval tab decides on this, not lead.purchase.
        $approvalRequest = ApprovalRequest::query()
            ->where('module', 'purchase-approval')
            ->where('subject_type', $purchaseLead->getMorphClass())
            ->where('subject_id', $purchaseLead->id)
            ->with('steps.role:id,name')
            ->latest('id')
            ->first();

        return Inertia::render('admin/purchase/leads/Show', [
            'lead' => $purchaseLead,
            'approvalRequest' => $approvalRequest,
            'statuses' => PurchaseLeadStatus::options(),
            'allowedTransitions' => array_values(array_map(
                fn (PurchaseLeadStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                // Purchase-approval and possession are reached through their own
                // actions (which create the purchase record / stock), not the
                // generic status control — so keep them out of that dropdown.
                array_filter(
                    $purchaseLead->status->allowedTransitions(),
                    fn (PurchaseLeadStatus $s) => ! $s->requiresDedicatedAction(),
                ),
            )),
            'employees' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'can' => [
                'update' => $request->user()->can('update', $purchaseLead),
                'assign' => $request->user()->can('assign', $purchaseLead),
                'viewKyc' => $canViewKyc,
                'viewCost' => $canViewCost,
                'createInspection' => $request->user()->can('inspections.create'),
                'saveValuation' => $request->user()->can('valuations.create'),
                'requestApproval' => $request->user()->can('purchase-approvals.create'),
                'decideApproval' => $request->user()->can('approvals.approve'),
                'recordPayment' => $request->user()->can('seller-payments.create'),
                'approvePayment' => $request->user()->can('seller-payments.approve'),
                'confirmPossession' => $request->user()->can('possessions.create'),
                'generateAgreement' => $request->user()->can('vehicle-purchases.create'),
            ],
        ]);
    }

    public function update(PurchaseLeadRequest $request, PurchaseLead $purchaseLead): RedirectResponse
    {
        $purchaseLead->update($request->validated());

        return back()->with('success', 'Lead updated.');
    }

    public function transition(Request $request, PurchaseLead $purchaseLead): RedirectResponse
    {
        $this->authorize('update', $purchaseLead);

        $data = $request->validate([
            'status' => ['required', 'string'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $target = PurchaseLeadStatus::from($data['status']);

        // Guard the record-creating milestones: these must go through their
        // dedicated action so the VehiclePurchase / stock entry is actually created.
        if ($target->requiresDedicatedAction()) {
            $message = match ($target) {
                PurchaseLeadStatus::PurchaseApprovalPending => 'Use “Request Purchase Approval” — that opens the approval so it can be decided.',
                PurchaseLeadStatus::Purchased => 'Use “Confirm Possession” to complete the purchase — that step creates the stock entry.',
                default => 'Approve the purchase from its approval request — that step creates the purchase record.',
            };

            return back()->with('error', $message);
        }

        if ($target->isLost() && empty($data['lost_reason'])) {
            return back()->withErrors(['lost_reason' => 'A reason is required when marking a lead as lost.']);
        }

        if ($target->isLost()) {
            $purchaseLead->update(['lost_reason' => $data['lost_reason']]);
        }

        $this->workflow->transition($purchaseLead, $target, $request->user(), $data['remarks'] ?? null);

        return back()->with('success', 'Lead status updated to '.$target->label().'.');
    }

    public function assign(Request $request, PurchaseLead $purchaseLead): RedirectResponse
    {
        $this->authorize('assign', $purchaseLead);

        $data = $request->validate([
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
        ]);

        $purchaseLead->update(['assigned_to' => $data['assigned_to']]);

        return back()->with('success', 'Lead reassigned.');
    }

    private function formProps(): array
    {
        return [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'employees' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }
}

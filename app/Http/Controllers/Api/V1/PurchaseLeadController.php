<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\PurchaseLeads\Actions\CreatePurchaseLeadAction;
use App\Domain\PurchaseLeads\Enums\PurchaseLeadStatus;
use App\Domain\PurchaseLeads\Models\PurchaseLead;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\PurchaseLeadRequest;
use App\Http\Resources\PurchaseLeadResource;
use App\Support\ApiResponse;
use App\Support\Workflow\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseLeadController extends Controller
{
    public function __construct(
        private readonly ScopeService $scopes,
        private readonly WorkflowService $workflow,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseLead::class);

        $leads = PurchaseLead::query()
            ->with(['assignee:id,name', 'branch:id,name'])
            ->tap(fn ($q) => $this->scopes->apply($q, $request->user(), [
                'branch' => 'branch_id', 'assigned' => 'assigned_to', 'owner' => 'created_by',
            ]))
            ->when($request->query('filter.status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('lead_number', 'like', "%{$s}%")
                ->orWhere('seller_name', 'like', "%{$s}%")
                ->orWhere('mobile', 'like', "%{$s}%")))
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return ApiResponse::paginated(
            $leads->setCollection($leads->getCollection()->map(fn ($l) => (new PurchaseLeadResource($l))->resolve())),
        );
    }

    public function store(PurchaseLeadRequest $request, CreatePurchaseLeadAction $action): JsonResponse
    {
        $data = $request->validated();
        $data['branch_id'] ??= $request->user()->branch_id;
        $data['source'] ??= 'mobile';

        $lead = $action->execute($data, $request->user());

        return ApiResponse::success(
            (new PurchaseLeadResource($lead))->resolve(),
            'Purchase lead created.',
            status: 201,
        );
    }

    public function show(Request $request, PurchaseLead $purchaseLead): JsonResponse
    {
        $this->authorize('view', $purchaseLead);

        $purchaseLead->load(['assignee:id,name', 'branch:id,name', 'followups.user:id,name', 'verifications', 'valuation', 'latestInspection']);

        return ApiResponse::success([
            ...(new PurchaseLeadResource($purchaseLead))->resolve(),
            'followups' => $purchaseLead->followups->map(fn ($f) => [
                'id' => $f->id,
                'contact_mode' => $f->contact_mode,
                'outcome' => $f->outcome,
                'remarks' => $f->remarks,
                'next_follow_up_at' => $f->next_follow_up_at?->toIso8601String(),
                'by' => $f->user?->name,
                'created_at' => $f->created_at->toIso8601String(),
            ]),
            'allowed_transitions' => array_map(
                fn (PurchaseLeadStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                $purchaseLead->status->allowedTransitions(),
            ),
        ]);
    }

    public function followup(Request $request, PurchaseLead $purchaseLead): JsonResponse
    {
        $this->authorize('update', $purchaseLead);

        $data = $request->validate([
            'contact_mode' => ['required', 'string', 'in:call,whatsapp,visit,other'],
            'outcome' => ['nullable', 'string', 'max:40'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'next_follow_up_at' => ['nullable', 'date'],
        ]);

        $purchaseLead->followups()->create([...$data, 'user_id' => $request->user()->id]);

        if (! empty($data['next_follow_up_at'])) {
            $purchaseLead->update(['next_follow_up_at' => $data['next_follow_up_at']]);
        }

        return ApiResponse::success(null, 'Follow-up added.', status: 201);
    }

    public function transition(Request $request, PurchaseLead $purchaseLead): JsonResponse
    {
        $this->authorize('update', $purchaseLead);

        $data = $request->validate([
            'status' => ['required', 'string'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $target = PurchaseLeadStatus::from($data['status']);

        if ($target->isLost() && empty($data['lost_reason'])) {
            return ApiResponse::error('A lost reason is required.', 422, ['errors' => ['lost_reason' => ['A reason is required.']]]);
        }

        if ($target->isLost()) {
            $purchaseLead->update(['lost_reason' => $data['lost_reason']]);
        }

        $this->workflow->transition($purchaseLead, $target, $request->user(), $data['remarks'] ?? null);

        return ApiResponse::success(['status' => $target->value], 'Status updated.');
    }
}

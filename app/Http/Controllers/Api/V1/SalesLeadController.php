<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Inventory\Models\Vehicle;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Domain\SalesLeads\Actions\LogCallAction;
use App\Domain\SalesLeads\Enums\CallOutcome;
use App\Domain\SalesLeads\Enums\SalesLeadStatus;
use App\Domain\SalesLeads\Models\LeadLostReason;
use App\Domain\SalesLeads\Models\SalesLead;
use App\Domain\TestDrives\Actions\TestDriveAction;
use App\Domain\Visits\Actions\ScheduleVisitAction;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\Workflow\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalesLeadController extends Controller
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SalesLead::class);

        $leads = SalesLead::query()
            ->with(['interestedVehicle:id,stock_number,make,model'])
            ->tap(fn ($q) => $this->scopes->apply($q, $request->user(), ['branch' => 'branch_id', 'assigned' => 'telecaller_id', 'owner' => 'created_by']))
            ->when($request->query('queue') === 'mine', fn ($q) => $q->where('telecaller_id', $request->user()->id))
            ->when($request->query('queue') === 'due', fn ($q) => $q
                ->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now()->endOfDay())
                ->whereIn('status', SalesLeadStatus::openValues()))
            ->when($request->query('filter.status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('mobile', 'like', "%{$s}%")))
            ->orderBy('next_follow_up_at')
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return ApiResponse::paginated($leads->through(fn (SalesLead $l) => $this->row($l)));
    }

    public function followUpQueue(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SalesLead::class);

        $leads = SalesLead::query()
            ->tap(fn ($q) => $this->scopes->apply($q, $request->user(), ['branch' => 'branch_id', 'assigned' => 'telecaller_id', 'owner' => 'created_by']))
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', now()->endOfDay())
            ->whereIn('status', SalesLeadStatus::openValues())
            ->orderBy('next_follow_up_at')
            ->limit(100)->get();

        return ApiResponse::success($leads->map(fn (SalesLead $l) => $this->row($l)));
    }

    public function show(Request $request, SalesLead $salesLead): JsonResponse
    {
        $this->authorize('view', $salesLead);

        $salesLead->load(['customer:id,customer_code,name,mobile', 'followups.user:id,name', 'interestedVehicle:id,stock_number,make,model']);

        return ApiResponse::success([
            ...$this->row($salesLead),
            'email' => $salesLead->email,
            'budget' => ['min' => $salesLead->budget_min, 'max' => $salesLead->budget_max],
            'finance_required' => $salesLead->finance_required,
            'exchange_required' => $salesLead->exchange_required,
            'remarks' => $salesLead->remarks,
            'allowed_transitions' => array_map(fn (SalesLeadStatus $s) => $s->value, $salesLead->status->allowedTransitions()),
            'followups' => $salesLead->followups->map(fn ($f) => [
                'outcome' => $f->call_outcome?->value,
                'channel' => $f->channel,
                'remarks' => $f->remarks,
                'by' => $f->user?->name,
                'at' => $f->created_at->toDateTimeString(),
            ]),
            'call_outcomes' => CallOutcome::options(),
            'lost_reasons' => LeadLostReason::query()->where('is_active', true)->get(['id', 'label']),
        ]);
    }

    public function logCall(Request $request, SalesLead $salesLead, LogCallAction $action): JsonResponse
    {
        $this->authorize('update', $salesLead);

        $data = $request->validate([
            'outcome' => ['required', Rule::enum(CallOutcome::class)],
            'channel' => ['nullable', 'string', 'in:call,whatsapp,sms,email,visit'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'next_follow_up_at' => ['nullable', 'date'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'lost_reason_id' => ['nullable', 'integer', 'exists:lead_lost_reasons,id'],
        ]);

        $action->execute($salesLead, CallOutcome::from($data['outcome']), $data, $request->user());

        return ApiResponse::success(['status' => $salesLead->fresh()->status->value], 'Call logged.');
    }

    public function transition(Request $request, SalesLead $salesLead, WorkflowService $workflow): JsonResponse
    {
        $this->authorize('update', $salesLead);

        $data = $request->validate([
            'status' => ['required', Rule::enum(SalesLeadStatus::class)],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'lost_reason_id' => ['nullable', 'integer', 'exists:lead_lost_reasons,id'],
        ]);

        $target = SalesLeadStatus::from($data['status']);

        if ($target->isLost() && empty($data['lost_reason_id'])) {
            throw ValidationException::withMessages(['lost_reason_id' => 'A reason is required when marking the lead as lost.']);
        }
        if ($target->isLost()) {
            $salesLead->update(['lost_reason_id' => $data['lost_reason_id']]);
        }

        $workflow->transition($salesLead, $target, $request->user(), $data['remarks'] ?? null);

        return ApiResponse::success(['status' => $salesLead->fresh()->status->value], 'Status updated.');
    }

    public function scheduleVisit(Request $request, SalesLead $salesLead, ScheduleVisitAction $action): JsonResponse
    {
        $this->authorize('update', $salesLead);

        $data = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $visit = $action->schedule($salesLead, $data, $request->user());

        return ApiResponse::success(['id' => $visit->id, 'visit_number' => $visit->visit_number], 'Visit scheduled.', status: 201);
    }

    public function scheduleTestDrive(Request $request, SalesLead $salesLead, TestDriveAction $action): JsonResponse
    {
        $this->authorize('update', $salesLead);

        $data = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'scheduled_at' => ['required', 'date'],
            'driving_licence_number' => ['nullable', 'string', 'max:255'],
        ]);

        $td = $action->schedule($salesLead, Vehicle::query()->findOrFail($data['vehicle_id']), $data, $request->user());

        return ApiResponse::success(['id' => $td->id, 'td_number' => $td->td_number], 'Test drive scheduled.', status: 201);
    }

    public function performance(Request $request): JsonResponse
    {
        $user = $request->user();
        $from = now()->subDays(29)->startOfDay();

        $base = SalesLead::query()->where('telecaller_id', $user->id);

        return ApiResponse::success([
            'assigned' => (clone $base)->where('created_at', '>=', $from)->count(),
            'contacted' => (clone $base)->whereNotNull('first_response_at')->where('created_at', '>=', $from)->count(),
            'interested' => (clone $base)->where('status', SalesLeadStatus::Interested->value)->count(),
            'followups_due' => (clone $base)->whereNotNull('next_follow_up_at')
                ->where('next_follow_up_at', '<=', now()->endOfDay())
                ->whereIn('status', SalesLeadStatus::openValues())->count(),
            'lost' => (clone $base)->whereIn('status', [SalesLeadStatus::Lost->value, SalesLeadStatus::WrongNumber->value, SalesLeadStatus::Duplicate->value])->count(),
        ]);
    }

    private function row(SalesLead $l): array
    {
        return [
            'id' => $l->id,
            'lead_number' => $l->lead_number,
            'name' => $l->name,
            'mobile' => $l->mobile,
            'city' => $l->city,
            'status' => $l->status->value,
            'priority' => $l->priority,
            'source' => $l->source,
            'next_follow_up_at' => $l->next_follow_up_at?->toDateTimeString(),
            'overdue' => $l->next_follow_up_at !== null && $l->next_follow_up_at->isPast(),
            'interested_vehicle' => $l->interestedVehicle?->only(['id', 'stock_number', 'make', 'model']),
        ];
    }
}

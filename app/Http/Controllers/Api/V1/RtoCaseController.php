<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\RolesPermissions\Services\ScopeService;
use App\Domain\RTO\Actions\RtoCaseAction;
use App\Domain\RTO\Enums\RtoStatus;
use App\Domain\RTO\Models\RtoCase;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RtoCaseController extends Controller
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RtoCase::class);

        $cases = RtoCase::query()
            ->with(['vehicle:id,stock_number,make,model,registration_number', 'buyer:id,name,mobile'])
            ->tap(fn ($q) => $this->scopes->apply($q, $request->user(), ['branch' => 'branch_id', 'assigned' => 'assigned_to', 'owner' => 'created_by']))
            ->when($request->query('filter.status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->boolean('mine'), fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return ApiResponse::paginated($cases->through(fn (RtoCase $c) => $this->row($c)));
    }

    public function show(Request $request, RtoCase $rtoCase): JsonResponse
    {
        $this->authorize('view', $rtoCase);

        $rtoCase->load([
            'vehicle:id,stock_number,make,model,registration_number', 'buyer:id,name,mobile',
            'movements.mover:id,name', 'expenses', 'holds', 'documents',
        ]);

        return ApiResponse::success([
            ...$this->row($rtoCase),
            'from_rto' => $rtoCase->from_rto,
            'to_rto' => $rtoCase->to_rto,
            'application_number' => $rtoCase->application_number,
            'total_expenses' => $rtoCase->totalExpenses(),
            'hold_amount' => $rtoCase->hold_amount,
            'allowed_transitions' => array_map(fn ($s) => $s->value, $rtoCase->status->allowedTransitions()),
            'movements' => $rtoCase->movements->map(fn ($m) => [
                'document' => $m->document, 'from_holder' => $m->from_holder, 'to_holder' => $m->to_holder,
                'moved_by' => $m->mover?->name, 'moved_at' => $m->moved_at?->toIso8601String(),
            ]),
            'expenses' => $rtoCase->expenses->map(fn ($e) => ['head' => $e->head, 'amount' => $e->amount, 'reference' => $e->reference]),
            'holds' => $rtoCase->holds->map(fn ($h) => ['id' => $h->id, 'amount' => $h->amount, 'reason' => $h->reason, 'status' => $h->status]),
        ]);
    }

    public function transition(Request $request, RtoCase $rtoCase, RtoCaseAction $action): JsonResponse
    {
        $this->authorize('update', $rtoCase);

        $data = $request->validate([
            'status' => ['required', Rule::enum(RtoStatus::class)],
            'application_number' => ['nullable', 'string', 'max:255'],
            'to_rto' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $rtoCase->fill(array_filter([
            'application_number' => $data['application_number'] ?? null,
            'to_rto' => $data['to_rto'] ?? null,
        ], fn ($v) => $v !== null))->save();

        $action->transition($rtoCase, RtoStatus::from($data['status']), $request->user(), $data['remarks'] ?? null);

        return ApiResponse::success(['status' => $rtoCase->fresh()->status->value], 'RTO status updated.');
    }

    public function recordMovement(Request $request, RtoCase $rtoCase, RtoCaseAction $action): JsonResponse
    {
        $this->authorize('update', $rtoCase);

        $data = $request->validate([
            'document' => ['required', 'string', 'max:255'],
            'to_holder' => ['required', 'string', 'max:255'],
            'from_holder' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $movement = $action->recordMovement($rtoCase, $data['document'], $data['to_holder'], $request->user(), $data['from_holder'] ?? null, $data['remarks'] ?? null);

        return ApiResponse::success(['id' => $movement->id], 'Movement recorded.', status: 201);
    }

    public function addExpense(Request $request, RtoCase $rtoCase, RtoCaseAction $action): JsonResponse
    {
        $this->authorize('update', $rtoCase);

        $data = $request->validate([
            'head' => ['required', 'string', 'max:40'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $expense = $action->addExpense($rtoCase, $data['head'], (float) $data['amount'], $request->user(), $data['reference'] ?? null);

        return ApiResponse::success(['id' => $expense->id], 'Expense recorded.', status: 201);
    }

    private function row(RtoCase $c): array
    {
        return [
            'id' => $c->id,
            'rto_number' => $c->rto_number,
            'vehicle' => $c->vehicle?->only(['id', 'stock_number', 'make', 'model', 'registration_number']),
            'buyer' => $c->buyer?->only(['id', 'name', 'mobile']),
            'status' => $c->status->value,
            'status_label' => $c->status->label(),
            'expected_completion' => $c->expected_completion?->toDateString(),
        ];
    }
}

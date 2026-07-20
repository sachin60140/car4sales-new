<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finance\Actions\FinanceApplicationAction;
use App\Domain\Finance\Enums\FinanceStatus;
use App\Domain\Finance\Models\FinanceApplication;
use App\Domain\RolesPermissions\Services\ScopeService;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    public function __construct(private readonly ScopeService $scopes) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FinanceApplication::class);

        $applications = FinanceApplication::query()
            ->with(['customer:id,name,mobile', 'lender:id,name'])
            ->tap(fn ($q) => $this->scopes->apply($q, $request->user(), ['branch' => 'branch_id', 'assigned' => 'assigned_to', 'owner' => 'created_by']))
            ->when($request->query('filter.status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return ApiResponse::paginated($applications->through(fn (FinanceApplication $f) => $this->row($f)));
    }

    public function show(Request $request, FinanceApplication $finance): JsonResponse
    {
        $this->authorize('view', $finance);

        $finance->load(['customer:id,name,mobile', 'lender:id,name', 'disbursements']);

        return ApiResponse::success([
            ...$this->row($finance),
            'loan_amount' => $finance->loan_amount,
            'sanction_amount' => $finance->sanction_amount,
            'emi' => $finance->emi,
            'disbursed_amount' => $finance->disbursed_amount,
            'allowed_transitions' => array_map(fn ($s) => $s->value, $finance->status->allowedTransitions()),
        ]);
    }

    public function transition(Request $request, FinanceApplication $finance, FinanceApplicationAction $action): JsonResponse
    {
        $this->authorize('update', $finance);

        $data = $request->validate([
            'status' => ['required', Rule::enum(FinanceStatus::class)],
            'sanction_amount' => ['nullable', 'numeric', 'min:0'],
            'emi' => ['nullable', 'numeric', 'min:0'],
            'rejection_reason' => ['nullable', 'string', 'max:500'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $action->transition($finance, FinanceStatus::from($data['status']), $data, $request->user());

        return ApiResponse::success(['status' => $finance->fresh()->status->value], 'Finance status updated.');
    }

    public function disburse(Request $request, FinanceApplication $finance, FinanceApplicationAction $action): JsonResponse
    {
        $this->authorize('update', $finance);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'utr' => ['nullable', 'string', 'max:255'],
        ]);

        $disbursement = $action->disburse($finance, (float) $data['amount'], $data['utr'] ?? null, $request->user());

        return ApiResponse::success(['disbursement_number' => $disbursement->disbursement_number], 'Disbursement recorded.', status: 201);
    }

    private function row(FinanceApplication $f): array
    {
        return [
            'id' => $f->id,
            'application_number' => $f->application_number,
            'customer' => $f->customer?->only(['id', 'name', 'mobile']),
            'lender' => $f->lender?->only(['id', 'name']),
            'status' => $f->status->value,
        ];
    }
}

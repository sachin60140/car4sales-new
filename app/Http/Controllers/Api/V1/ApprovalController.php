<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Approvals\Services\ApprovalEngine;
use App\Domain\PurchaseApprovals\Actions\CompletePurchaseFromApprovalAction;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    /**
     * The central approval inbox — requests currently awaiting the caller's roles.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ApprovalRequest::class);

        $roleIds = $request->user()->roles->pluck('id');

        $approvals = ApprovalRequest::query()
            ->with(['requester:id,name', 'steps.role:id,name'])
            ->where('status', 'pending')
            ->when(! $request->user()->hasRole('Super Admin'),
                fn ($q) => $q->whereIn('current_role_id', $roleIds))
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return ApiResponse::paginated($approvals->through(fn (ApprovalRequest $a) => [
            'id' => $a->id,
            'approval_number' => $a->approval_number,
            'module' => $a->module,
            'requested_amount' => $a->requested_amount,
            'recommended_amount' => $a->recommended_amount,
            'reasons' => $a->reasons,
            'requested_by' => $a->requester?->name,
            'created_at' => $a->created_at->toIso8601String(),
        ]));
    }

    public function decide(
        Request $request,
        ApprovalRequest $approvalRequest,
        ApprovalEngine $engine,
        CompletePurchaseFromApprovalAction $complete,
    ): JsonResponse {
        $this->authorize('decide', $approvalRequest);

        $data = $request->validate([
            'decision' => ['required', 'string', 'in:approve,reject'],
            'approved_amount' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['decision'] === 'approve') {
            $result = $engine->approve(
                $approvalRequest,
                $request->user(),
                isset($data['approved_amount']) ? (float) $data['approved_amount'] : null,
                $data['remarks'] ?? null,
            );

            if ($result->status->value === 'approved' && $result->module === 'purchase-approval') {
                $complete->execute($result, $request->user());
            }

            return ApiResponse::success(['status' => $result->status->value], 'Approval recorded.');
        }

        $engine->reject($approvalRequest, $request->user(), $data['remarks'] ?? 'Rejected');

        return ApiResponse::success(['status' => 'rejected'], 'Approval rejected.');
    }
}

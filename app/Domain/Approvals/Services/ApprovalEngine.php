<?php

namespace App\Domain\Approvals\Services;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\Approvals\Enums\ApprovalStatus;
use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\RolesPermissions\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Builds and drives multi-step approval chains. The chain is assembled from the
 * approval_limits configured per role for a module; any step whose max_amount is
 * below the requested amount is retained (escalation), and the final step (null
 * max_amount = unlimited) always terminates the chain.
 */
class ApprovalEngine
{
    /**
     * Default purchase-approval escalation order.
     *
     * @var array<int, string>
     */
    public const PURCHASE_CHAIN = ['Purchase Manager', 'Branch Manager', 'Director'];

    public function __construct(private readonly NumberSequenceService $sequences) {}

    /**
     * Open an approval request for a subject with a role-based escalation chain.
     *
     * @param  array<int, string>  $roleChain  ordered role names
     * @param  array<int, string>  $reasons    risk flags forcing/annotating the approval
     */
    public function open(
        Model $subject,
        string $module,
        float $requestedAmount,
        User $requester,
        array $roleChain,
        ?float $recommendedAmount = null,
        array $reasons = [],
        ?string $reason = null,
        ?int $branchId = null,
    ): ApprovalRequest {
        return DB::transaction(function () use ($subject, $module, $requestedAmount, $requester, $roleChain, $recommendedAmount, $reasons, $reason, $branchId) {
            $steps = $this->buildSteps($module, $requestedAmount, $roleChain, $reasons !== []);

            if ($steps === []) {
                throw new RuntimeException("No approval chain could be built for module [{$module}].");
            }

            $request = new ApprovalRequest([
                'approval_number' => $this->sequences->next('approval'),
                'module' => $module,
                'type' => $subject->getTable(),
                'branch_id' => $branchId,
                'requested_by' => $requester->id,
                'requested_amount' => $requestedAmount,
                'recommended_amount' => $recommendedAmount,
                'reason' => $reason,
                'reasons' => $reasons,
                'status' => ApprovalStatus::Pending,
                'current_role_id' => $steps[0]['role_id'],
            ]);
            $request->subject()->associate($subject);
            $request->save();

            foreach ($steps as $index => $step) {
                $request->steps()->create([
                    'sequence' => $index + 1,
                    'role_id' => $step['role_id'],
                    'status' => 'pending',
                ]);
            }

            app(\App\Domain\Notifications\Services\NotificationDispatcher::class)->approvalRequested($request);

            return $request->load('steps');
        });
    }

    /**
     * Approve the current step. Advances to the next step, or finalises the request.
     */
    public function approve(ApprovalRequest $request, User $approver, ?float $approvedAmount = null, ?string $remarks = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $approver, $approvedAmount, $remarks) {
            $step = $request->currentStep();

            if ($step === null || $request->status !== ApprovalStatus::Pending) {
                throw new RuntimeException('This approval has no pending step.');
            }

            $this->assertCanAct($request, $approver, $step);

            $step->update([
                'status' => 'approved',
                'acted_by' => $approver->id,
                'acted_at' => now(),
                'remarks' => $remarks,
                'user_id' => $approver->id,
            ]);

            $next = $request->steps()->where('status', 'pending')->orderBy('sequence')->first();

            if ($next !== null) {
                $request->update(['current_role_id' => $next->role_id]);
            } else {
                $request->update([
                    'status' => ApprovalStatus::Approved,
                    'approved_amount' => $approvedAmount ?? $request->requested_amount,
                    'current_role_id' => null,
                    'decided_at' => now(),
                ]);
                $this->notifySubject($request, 'approved', $approver);
            }

            return $request->fresh('steps');
        });
    }

    public function reject(ApprovalRequest $request, User $approver, string $remarks): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $approver, $remarks) {
            $step = $request->currentStep();

            if ($step === null || $request->status !== ApprovalStatus::Pending) {
                throw new RuntimeException('This approval has no pending step.');
            }

            $this->assertCanAct($request, $approver, $step);

            $step->update([
                'status' => 'rejected',
                'acted_by' => $approver->id,
                'acted_at' => now(),
                'remarks' => $remarks,
                'user_id' => $approver->id,
            ]);

            $request->update([
                'status' => ApprovalStatus::Rejected,
                'current_role_id' => null,
                'decided_at' => now(),
            ]);

            $this->notifySubject($request, 'rejected', $approver);

            return $request->fresh('steps');
        });
    }

    /**
     * @param  array<int, string>  $roleChain
     * @return array<int, array{role_id: int}>
     */
    private function buildSteps(string $module, float $amount, array $roleChain, bool $forceTop): array
    {
        $roles = Role::query()
            ->whereIn('name', $roleChain)
            ->with(['approvalLimits' => fn ($q) => $q->where('module', $module)])
            ->get()
            ->keyBy('name');

        $steps = [];

        foreach ($roleChain as $roleName) {
            $role = $roles->get($roleName);

            if ($role === null) {
                continue;
            }

            $limit = $role->approvalLimits->first();
            $max = $limit?->max_amount;

            $steps[] = ['role_id' => $role->id, 'max' => $max];

            // A role with unlimited authority (null max) terminates the chain,
            // unless a risk flag forces escalation to the very top.
            if ($max === null && ! $forceTop) {
                break;
            }

            if ($max !== null && (float) $max >= $amount && ! $forceTop) {
                break;
            }
        }

        return array_map(fn ($s) => ['role_id' => $s['role_id']], $steps);
    }

    private function assertCanAct(ApprovalRequest $request, User $user, $step): void
    {
        if ($user->hasRole('Super Admin')) {
            return;
        }

        if ($step->role_id !== null && ! $user->hasRole($step->role->name)) {
            throw new RuntimeException('You are not authorised to act on this approval step.');
        }
    }

    private function notifySubject(ApprovalRequest $request, string $decision, User $approver): void
    {
        app(\App\Domain\Notifications\Services\NotificationDispatcher::class)->approvalDecided($request, $decision);

        $subject = $request->subject;

        if ($subject !== null && method_exists($subject, 'onApprovalDecided')) {
            $subject->onApprovalDecided($request, $decision, $approver);
        }
    }
}

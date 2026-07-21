<?php

namespace App\Domain\PurchaseApprovals\Actions;

use App\Domain\Approvals\Enums\ApprovalStatus;
use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Approvals\Services\ApprovalEngine;
use App\Domain\PurchaseLeads\Enums\PurchaseLeadStatus;
use App\Domain\PurchaseLeads\Models\PurchaseLead;
use App\Models\User;
use App\Support\Workflow\WorkflowService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Raises a purchase approval for a lead. Evaluates risk flags to decide whether
 * the approval must escalate to the top of the chain (Director/Owner).
 */
class RequestPurchaseApprovalAction
{
    public function __construct(
        private readonly ApprovalEngine $engine,
        private readonly WorkflowService $workflow,
    ) {}

    public function execute(PurchaseLead $lead, float $requestedAmount, User $requester, ?string $reason = null): ApprovalRequest
    {
        // One open approval per lead — prevents duplicate pending requests when
        // the button is clicked twice.
        $existing = ApprovalRequest::query()
            ->where('module', 'purchase-approval')
            ->where('subject_type', $lead->getMorphClass())
            ->where('subject_id', $lead->id)
            ->where('status', ApprovalStatus::Pending->value)
            ->exists();

        if ($existing) {
            throw new RuntimeException('A purchase approval is already pending for this lead.');
        }

        return DB::transaction(function () use ($lead, $requestedAmount, $requester, $reason) {
            $lead->loadMissing(['valuation', 'verifications', 'latestInspection']);

            $reasons = $this->riskFlags($lead, $requestedAmount);

            $request = $this->engine->open(
                subject: $lead,
                module: 'purchase-approval',
                requestedAmount: $requestedAmount,
                requester: $requester,
                roleChain: ApprovalEngine::PURCHASE_CHAIN,
                recommendedAmount: (float) ($lead->valuation?->recommended_price ?? 0),
                reasons: $reasons,
                reason: $reason,
                branchId: $lead->branch_id,
            );

            if ($lead->status !== PurchaseLeadStatus::PurchaseApprovalPending) {
                $this->workflow->transition($lead, PurchaseLeadStatus::PurchaseApprovalPending, $requester, 'Purchase approval requested', force: true);
            }

            return $request;
        });
    }

    /**
     * @return array<int, string>
     */
    private function riskFlags(PurchaseLead $lead, float $requestedAmount): array
    {
        $flags = [];
        $valuation = $lead->valuation;

        if ($valuation !== null) {
            if ((float) $valuation->recommended_price > 0 && $requestedAmount > (float) $valuation->recommended_price) {
                $flags[] = 'above_recommended_price';
            }

            if ((float) $valuation->expected_net_profit < 0) {
                $flags[] = 'negative_expected_profit';
            } elseif ((float) $valuation->expected_margin_pct < 5) {
                $flags[] = 'low_margin';
            }

            if ((float) $valuation->repair_estimate > 50000) {
                $flags[] = 'high_repair_estimate';
            }
        }

        if ($lead->loan_status === 'active') {
            $flags[] = 'active_hypothecation';
        } elseif ($lead->loan_status === 'closed_pending_noc') {
            $flags[] = 'pending_noc';
        }

        // Missing critical documents.
        $missing = $lead->verifications
            ->whereIn('type', ['rc_original', 'insurance'])
            ->whereNotIn('status', ['verified', 'received'])
            ->isNotEmpty();

        if ($missing) {
            $flags[] = 'missing_documents';
        }

        if ($lead->latestInspection?->result === 'management_approval') {
            $flags[] = 'inspection_flagged';
        }

        return $flags;
    }
}

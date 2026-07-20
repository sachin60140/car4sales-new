<?php

namespace App\Domain\PurchaseApprovals\Actions;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\PurchaseLeads\Enums\PurchaseLeadStatus;
use App\Domain\PurchaseLeads\Models\PurchaseLead;
use App\Domain\VehiclePurchases\Models\VehiclePurchase;
use App\Models\User;
use App\Support\Workflow\WorkflowService;
use Illuminate\Support\Facades\DB;

/**
 * On an approved purchase approval, moves the lead to PurchaseApproved and
 * creates the VehiclePurchase record that anchors agreement, payments and
 * possession.
 */
class CompletePurchaseFromApprovalAction
{
    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly WorkflowService $workflow,
    ) {}

    public function execute(ApprovalRequest $request, User $actor): VehiclePurchase
    {
        return DB::transaction(function () use ($request, $actor) {
            /** @var PurchaseLead $lead */
            $lead = $request->subject;
            $lead->loadMissing('valuation');

            $this->workflow->transition($lead, PurchaseLeadStatus::PurchaseApproved, $actor, 'Purchase approved ('.$request->approval_number.')', force: true);

            $agreedPrice = (float) ($request->approved_amount
                ?? $lead->valuation?->final_negotiated_price
                ?? $lead->valuation?->recommended_price
                ?? 0);

            $purchase = VehiclePurchase::query()->create([
                'purchase_number' => $this->sequences->next('purchase'),
                'purchase_lead_id' => $lead->id,
                'seller_id' => $lead->seller_id,
                'branch_id' => $lead->branch_id,
                'agreed_price' => $agreedPrice,
                'initial_expenses' => $this->initialExpenses($lead),
                'approval_request_id' => $request->id,
                'status' => 'approved',
                'created_by' => $actor->id,
            ]);

            $this->workflow->transition($lead, PurchaseLeadStatus::AgreementPending, $actor, 'Awaiting agreement generation');

            return $purchase;
        });
    }

    private function initialExpenses(PurchaseLead $lead): float
    {
        $v = $lead->valuation;

        if ($v === null) {
            return 0.0;
        }

        return (float) $v->rto_expense
            + (float) $v->documentation_expense
            + (float) $v->transportation_expense
            + (float) $v->insurance_expense;
    }
}

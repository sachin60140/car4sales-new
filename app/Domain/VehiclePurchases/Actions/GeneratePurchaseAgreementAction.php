<?php

namespace App\Domain\VehiclePurchases\Actions;

use App\Domain\Documents\Services\DocumentGenerator;
use App\Domain\PurchaseLeads\Enums\PurchaseLeadStatus;
use App\Domain\VehiclePurchases\Models\VehiclePurchase;
use App\Models\User;
use App\Support\Workflow\WorkflowService;
use Illuminate\Support\Facades\DB;

class GeneratePurchaseAgreementAction
{
    public function __construct(
        private readonly DocumentGenerator $documents,
        private readonly WorkflowService $workflow,
    ) {}

    public function execute(VehiclePurchase $purchase, User $actor): VehiclePurchase
    {
        return DB::transaction(function () use ($purchase, $actor) {
            $purchase->loadMissing(['purchaseLead', 'seller', 'branch']);
            $lead = $purchase->purchaseLead;

            $document = $this->documents->generate(
                templateKey: 'purchase_agreement',
                view: 'documents.purchase_agreement',
                data: [
                    'purchase' => $purchase,
                    'lead' => $lead,
                    'seller' => $purchase->seller,
                    'branch' => $purchase->branch,
                ],
                subject: $purchase,
                generatedBy: $actor,
                referencePrefix: 'AGR',
            );

            $purchase->update([
                'agreement_document_id' => $document->id,
                'status' => 'agreement_generated',
            ]);

            if ($lead->status === PurchaseLeadStatus::AgreementPending) {
                $this->workflow->transition($lead, PurchaseLeadStatus::PaymentPending, $actor, 'Agreement generated ('.$document->document_number.')');
            }

            return $purchase->fresh();
        });
    }
}

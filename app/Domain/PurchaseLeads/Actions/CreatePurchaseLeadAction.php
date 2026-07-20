<?php

namespace App\Domain\PurchaseLeads\Actions;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\PurchaseLeads\Enums\PurchaseLeadStatus;
use App\Domain\PurchaseLeads\Models\PurchaseLead;
use App\Domain\VehicleVerification\Services\VerificationChecklist;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreatePurchaseLeadAction
{
    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly VerificationChecklist $checklist,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?User $creator = null): PurchaseLead
    {
        return DB::transaction(function () use ($data, $creator) {
            $lead = new PurchaseLead([
                ...$data,
                'lead_number' => $this->sequences->next('purchase_lead'),
                'status' => $data['status'] ?? PurchaseLeadStatus::New->value,
                'created_by' => $creator?->id,
            ]);
            $lead->save();

            $lead->writeStatusHistory(null, $lead->status->value, $creator, 'Lead created');

            // Seed the standard vehicle-document verification checklist.
            $this->checklist->seed($lead);

            return $lead;
        });
    }
}

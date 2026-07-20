<?php

namespace App\Domain\Inspections\Actions;

use App\Domain\Inspections\Enums\InspectionStatus;
use App\Domain\Inspections\Models\VehicleInspection;
use App\Domain\PurchaseLeads\Enums\PurchaseLeadStatus;
use App\Models\User;
use App\Support\Workflow\WorkflowService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Locks and submits an inspection: totals repair estimates from sections,
 * marks the inspection submitted, and advances the lead to InspectionCompleted.
 * A locked inspection can no longer be edited.
 */
class SubmitInspectionAction
{
    public function __construct(private readonly WorkflowService $workflow) {}

    public function execute(VehicleInspection $inspection, User $actor, ?string $result = null): VehicleInspection
    {
        if ($inspection->isLocked()) {
            throw new RuntimeException('This inspection is already locked.');
        }

        return DB::transaction(function () use ($inspection, $actor, $result) {
            $inspection->loadMissing(['sections', 'purchaseLead']);

            $totalRepair = (float) $inspection->sections->sum('repair_estimate');

            $inspection->update([
                'total_repair_estimate' => $totalRepair,
                'result' => $result ?? $inspection->result ?? 'recommended',
                'completed_at' => now(),
                'locked_at' => now(),
                'status' => InspectionStatus::Submitted->value,
            ]);

            $lead = $inspection->purchaseLead;

            if (in_array($lead->status, [PurchaseLeadStatus::InspectionScheduled, PurchaseLeadStatus::Contacted], true)) {
                $this->workflow->transition($lead, PurchaseLeadStatus::InspectionCompleted, $actor, 'Inspection '.$inspection->inspection_number.' submitted', force: true);
            }

            return $inspection->fresh(['sections', 'media']);
        });
    }
}

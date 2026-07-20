<?php

namespace App\Domain\VehiclePurchases\Actions;

use App\Domain\Inventory\Actions\CreateStockFromPurchaseAction;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\PurchaseLeads\Enums\PurchaseLeadStatus;
use App\Domain\VehiclePurchases\Models\VehiclePurchase;
use App\Models\User;
use App\Support\Workflow\WorkflowService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Confirms vehicle possession and — atomically — triggers automatic stock entry.
 * The vehicle must physically be received before possession can be confirmed.
 *
 * @return array{purchase: VehiclePurchase, vehicle: Vehicle}
 */
class ConfirmPossessionAction
{
    public function __construct(
        private readonly CreateStockFromPurchaseAction $createStock,
        private readonly WorkflowService $workflow,
    ) {}

    /**
     * @param  array<string, mixed>  $checklist
     * @return array{purchase: VehiclePurchase, vehicle: Vehicle}
     */
    public function execute(VehiclePurchase $purchase, array $checklist, User $actor): array
    {
        if (empty($checklist['vehicle_received'])) {
            throw new RuntimeException('The vehicle must be physically received to confirm possession.');
        }

        return DB::transaction(function () use ($purchase, $checklist, $actor) {
            $purchase->loadMissing('purchaseLead');
            $lead = $purchase->purchaseLead;

            $purchase->possession()->updateOrCreate(
                ['vehicle_purchase_id' => $purchase->id],
                [
                    ...$checklist,
                    'possessed_at' => now(),
                    'received_by' => $actor->id,
                ],
            );

            // Move the lead to PossessionPending. Force is used because possession
            // can be recorded from AgreementPending or PaymentPending depending on
            // whether the agreement PDF step was taken.
            if ($lead->status !== PurchaseLeadStatus::PossessionPending) {
                $this->workflow->transition($lead, PurchaseLeadStatus::PossessionPending, $actor, 'Vehicle possession recorded', force: true);
            }

            $purchase->update(['status' => 'possession_pending', 'purchased_at' => now()]);

            // Automatic stock entry (guards duplicates internally).
            $vehicle = $this->createStock->execute($purchase, $actor);

            $this->workflow->transition($lead, PurchaseLeadStatus::Purchased, $actor, 'Vehicle purchased and stocked as '.$vehicle->stock_number);
            $purchase->update(['status' => 'completed']);

            return ['purchase' => $purchase->fresh(), 'vehicle' => $vehicle];
        });
    }
}

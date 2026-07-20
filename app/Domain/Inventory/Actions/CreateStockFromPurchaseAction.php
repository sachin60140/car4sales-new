<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\Inventory\Enums\VehicleStatus;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\VehiclePurchases\Models\VehiclePurchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates the stock (vehicle) record from a completed purchase, inside a
 * transaction, guarding against duplicates by registration/chassis/engine number.
 * Landed cost = agreed price + initial expenses.
 */
class CreateStockFromPurchaseAction
{
    public function __construct(private readonly NumberSequenceService $sequences) {}

    public function execute(VehiclePurchase $purchase, ?User $actor = null): Vehicle
    {
        return DB::transaction(function () use ($purchase, $actor) {
            $purchase->loadMissing('purchaseLead');
            $lead = $purchase->purchaseLead;

            if ($purchase->vehicle_id !== null) {
                throw new RuntimeException('Stock already exists for this purchase.');
            }

            $this->assertNotDuplicate($lead->registration_number, $lead->meta['chassis_number'] ?? null, $lead->meta['engine_number'] ?? null);

            $landedCost = (float) $purchase->agreed_price + (float) $purchase->initial_expenses;

            $vehicle = new Vehicle([
                'stock_number' => $this->sequences->next('stock'),
                'vehicle_purchase_id' => $purchase->id,
                'registration_number' => $lead->registration_number,
                'chassis_number' => $lead->meta['chassis_number'] ?? null,
                'engine_number' => $lead->meta['engine_number'] ?? null,
                'make' => $lead->make ?? 'Unknown',
                'model' => $lead->model ?? 'Unknown',
                'variant' => $lead->variant,
                'manufacturing_year' => $lead->manufacturing_year,
                'fuel_type' => $lead->fuel_type,
                'transmission' => $lead->transmission,
                'odometer_km' => $lead->odometer_km,
                'purchase_price' => $purchase->agreed_price,
                'landed_cost' => $landedCost,
                'branch_id' => $purchase->branch_id ?? $lead->branch_id,
                'inspection_grade' => optional($lead->latestInspection)->overall_grade,
                'refurb_required' => optional($lead->latestInspection)->result === 'recommended_with_repairs',
                'status' => VehicleStatus::InStock->value,
                'title' => trim(($lead->make ?? '').' '.($lead->model ?? '').' '.($lead->variant ?? '')),
                'created_by' => $actor?->id,
            ]);

            // Unique, human-friendly slug for future web publication.
            $vehicle->slug = Str::slug($vehicle->title.'-'.$vehicle->stock_number);
            $vehicle->save();

            $vehicle->writeStatusHistory(null, VehicleStatus::InStock->value, $actor, 'Auto stock entry from purchase '.$purchase->purchase_number);

            $purchase->update(['vehicle_id' => $vehicle->id]);

            return $vehicle;
        });
    }

    private function assertNotDuplicate(?string $registration, ?string $chassis, ?string $engine): void
    {
        $query = Vehicle::query();
        $hasCriteria = false;

        if ($registration !== null && $registration !== '') {
            $query->orWhere('registration_number', $registration);
            $hasCriteria = true;
        }

        if ($chassis !== null && $chassis !== '') {
            $query->orWhere('chassis_number', $chassis);
            $hasCriteria = true;
        }

        if ($engine !== null && $engine !== '') {
            $query->orWhere('engine_number', $engine);
            $hasCriteria = true;
        }

        if ($hasCriteria && $query->exists()) {
            throw new RuntimeException('A stock vehicle already exists with the same registration, chassis or engine number.');
        }
    }
}

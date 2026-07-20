<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\Vehicle;
use App\Domain\Inventory\Models\VehiclePrice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Updates a vehicle's asking or minimum selling price, recording the change in
 * price history for audit.
 */
class UpdateVehiclePriceAction
{
    /**
     * @param  'asking'|'minimum'  $priceType
     */
    public function execute(Vehicle $vehicle, string $priceType, float $newPrice, User $actor, ?string $reason = null): VehiclePrice
    {
        $column = $priceType === 'minimum' ? 'minimum_selling_price' : 'asking_price';

        return DB::transaction(function () use ($vehicle, $priceType, $column, $newPrice, $actor, $reason) {
            $old = $vehicle->{$column};

            $history = VehiclePrice::query()->create([
                'vehicle_id' => $vehicle->id,
                'price_type' => $priceType,
                'old_price' => $old,
                'new_price' => $newPrice,
                'reason' => $reason,
                'changed_by' => $actor->id,
            ]);

            $vehicle->update([$column => $newPrice]);

            return $history;
        });
    }
}

<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Branches\Models\Branch;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\Inventory\Models\VehicleMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Transfers a vehicle to another branch, recording the movement and updating the
 * vehicle's branch. Runs under a row lock to avoid concurrent transfers.
 */
class TransferVehicleAction
{
    public function execute(Vehicle $vehicle, Branch $toBranch, User $actor, ?string $parkingLocation = null, ?string $remarks = null): VehicleMovement
    {
        return DB::transaction(function () use ($vehicle, $toBranch, $actor, $parkingLocation, $remarks) {
            $locked = Vehicle::query()->whereKey($vehicle->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->branch_id === $toBranch->id) {
                throw new RuntimeException('The vehicle is already at that branch.');
            }

            $movement = VehicleMovement::query()->create([
                'vehicle_id' => $locked->id,
                'type' => MovementType::BranchTransfer->value,
                'from_branch_id' => $locked->branch_id,
                'to_branch_id' => $toBranch->id,
                'from_location' => $locked->parking_location,
                'to_location' => $parkingLocation,
                'moved_by' => $actor->id,
                'moved_at' => now(),
                'status' => 'completed',
                'remarks' => $remarks,
            ]);

            $locked->update([
                'branch_id' => $toBranch->id,
                'parking_location' => $parkingLocation ?? $locked->parking_location,
            ]);

            return $movement;
        });
    }
}

<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\Inventory\Models\VehicleMovement;
use App\Models\User;

/**
 * Records a non-transfer movement (workshop / test-drive / RTO / parking change).
 * These are "out" movements that can later be marked returned.
 */
class RecordVehicleMovementAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function out(Vehicle $vehicle, MovementType $type, array $data, User $actor): VehicleMovement
    {
        return $vehicle->movements()->create([
            'type' => $type->value,
            'from_branch_id' => $vehicle->branch_id,
            'from_location' => $vehicle->parking_location,
            'to_location' => $data['to_location'] ?? null,
            'reference' => $data['reference'] ?? null,
            'moved_by' => $actor->id,
            'moved_at' => now(),
            'expected_return_at' => $data['expected_return_at'] ?? null,
            'status' => 'out',
            'remarks' => $data['remarks'] ?? null,
        ]);
    }

    public function markReturned(VehicleMovement $movement, User $actor, ?string $parkingLocation = null): VehicleMovement
    {
        $movement->update([
            'returned_at' => now(),
            'status' => 'returned',
        ]);

        if ($parkingLocation !== null) {
            $movement->vehicle->update(['parking_location' => $parkingLocation]);
        }

        return $movement;
    }
}

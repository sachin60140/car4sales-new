<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\VehicleStatus;
use App\Domain\Inventory\Models\Vehicle;
use App\Models\User;
use App\Support\Workflow\WorkflowService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Toggles website / mobile publication for a vehicle. Publishing requires the
 * vehicle to be sellable and to have the minimum sale-ready data; the first web
 * publish moves the status to Published.
 */
class PublishVehicleAction
{
    public function __construct(private readonly WorkflowService $workflow) {}

    public function publish(Vehicle $vehicle, User $actor, bool $web = true, bool $mobile = true): Vehicle
    {
        return DB::transaction(function () use ($vehicle, $actor, $web, $mobile) {
            $this->assertPublishable($vehicle);

            $vehicle->update([
                'published_web' => $web,
                'published_mobile' => $mobile,
            ]);

            if ($web && $vehicle->status === VehicleStatus::ReadyForSale) {
                $this->workflow->transition($vehicle, VehicleStatus::Published, $actor, 'Published to website');
            }

            return $vehicle->fresh();
        });
    }

    public function unpublish(Vehicle $vehicle, User $actor): Vehicle
    {
        return DB::transaction(function () use ($vehicle, $actor) {
            $vehicle->update(['published_web' => false, 'published_mobile' => false]);

            if ($vehicle->status === VehicleStatus::Published) {
                $this->workflow->transition($vehicle, VehicleStatus::ReadyForSale, $actor, 'Unpublished from website');
            }

            return $vehicle->fresh();
        });
    }

    private function assertPublishable(Vehicle $vehicle): void
    {
        $errors = [];

        if (! in_array($vehicle->status, [VehicleStatus::ReadyForSale, VehicleStatus::Published], true)) {
            $errors[] = 'the vehicle must be Ready for Sale';
        }

        if ($vehicle->asking_price === null || (float) $vehicle->asking_price <= 0) {
            $errors[] = 'an asking price is required';
        }

        if ($vehicle->media()->count() === 0) {
            $errors[] = 'at least one photo is required';
        }

        if ($errors !== []) {
            throw new RuntimeException('Cannot publish: '.implode(', ', $errors).'.');
        }
    }
}

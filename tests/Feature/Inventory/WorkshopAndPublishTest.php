<?php

use App\Domain\Inventory\Actions\PublishVehicleAction;
use App\Domain\Inventory\Enums\VehicleStatus;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\Refurbishment\Actions\WorkshopJobAction;
use App\Domain\Refurbishment\Enums\WorkshopJobStatus;

function workshopVehicle(): Vehicle
{
    return Vehicle::query()->create([
        'stock_number' => 'STK-'.now()->year.'-'.fake()->unique()->numerify('W####'),
        'make' => 'Maruti', 'model' => 'Baleno',
        'purchase_price' => 500000, 'landed_cost' => 500000,
        'status' => VehicleStatus::InStock->value,
    ]);
}

it('runs a workshop job through to QC pass, posting the expense to landed cost', function () {
    $actor = superAdmin();
    $vehicle = workshopVehicle();
    $action = app(WorkshopJobAction::class);

    $job = $action->create($vehicle, ['type' => 'internal', 'description' => 'Denting & painting'], [
        ['description' => 'Bumper', 'work_type' => 'part', 'estimate' => 9000],
        ['description' => 'Paint', 'work_type' => 'labour', 'estimate' => 6000],
    ], $actor);

    expect((float) $job->estimate_total)->toBe(15000.0);

    $action->approve($job->fresh(), $actor, 15000);
    expect($vehicle->fresh()->status)->toBe(VehicleStatus::UnderRefurbishment);

    $action->start($job->fresh(), $actor);

    $items = $job->fresh('items')->items->map(fn ($i) => ['id' => $i->id, 'actual_amount' => 7000])->all();
    $completed = $action->complete($job->fresh(), $actor, $items, 'passed', 14000);

    expect($completed->status)->toBe(WorkshopJobStatus::QcPassed)
        ->and($vehicle->fresh()->status)->toBe(VehicleStatus::ReadyForSale)
        ->and((float) $vehicle->fresh()->landed_cost)->toBe(514000.0)
        ->and($vehicle->fresh()->expenses()->where('category', 'refurbishment')->count())->toBe(1);
});

it('sends a QC-failed job back to in-progress without posting an expense', function () {
    $actor = superAdmin();
    $vehicle = workshopVehicle();
    $action = app(WorkshopJobAction::class);

    $job = $action->create($vehicle, ['type' => 'internal'], [['description' => 'AC repair', 'work_type' => 'labour', 'estimate' => 5000]], $actor);
    $action->approve($job->fresh(), $actor);
    $action->start($job->fresh(), $actor);

    $items = $job->fresh('items')->items->map(fn ($i) => ['id' => $i->id, 'actual_amount' => 5000])->all();
    $failed = $action->complete($job->fresh(), $actor, $items, 'failed', 5000);

    expect($failed->status)->toBe(WorkshopJobStatus::QcFailed)
        ->and((float) $vehicle->fresh()->landed_cost)->toBe(500000.0)
        ->and($vehicle->fresh()->expenses()->count())->toBe(0);
});

it('blocks publishing a vehicle without price or photos', function () {
    $actor = superAdmin();
    $vehicle = Vehicle::query()->create([
        'stock_number' => 'STK-PUB-1', 'make' => 'M', 'model' => 'S',
        'status' => VehicleStatus::ReadyForSale->value, 'asking_price' => null,
    ]);

    app(PublishVehicleAction::class)->publish($vehicle, $actor);
})->throws(RuntimeException::class, 'Cannot publish');

it('publishes a ready vehicle with price and media, moving it to Published', function () {
    $actor = superAdmin();
    $vehicle = Vehicle::query()->create([
        'stock_number' => 'STK-PUB-2', 'make' => 'Hyundai', 'model' => 'i20',
        'status' => VehicleStatus::ReadyForSale->value, 'asking_price' => 650000,
    ]);
    $vehicle->media()->create(['type' => 'photo', 'file_path' => 'x.jpg']);

    $result = app(PublishVehicleAction::class)->publish($vehicle, $actor);

    expect($result->published_web)->toBeTrue()
        ->and($result->status)->toBe(VehicleStatus::Published);
});

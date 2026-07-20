<?php

use App\Domain\Inventory\Enums\VehicleStatus;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\Inventory\Services\VehicleExpenseService;
use App\Models\User;

function stockVehicle(array $overrides = []): Vehicle
{
    return Vehicle::query()->create(array_merge([
        'stock_number' => 'STK-'.now()->year.'-'.fake()->unique()->numerify('T####'),
        'make' => 'Maruti', 'model' => 'Swift',
        'purchase_price' => 400000, 'landed_cost' => 400000,
        'status' => VehicleStatus::InStock->value,
    ], $overrides));
}

it('adds an approved expense to the vehicle landed cost exactly once', function () {
    $actor = superAdmin();
    $vehicle = stockVehicle();
    $service = app(VehicleExpenseService::class);

    $expense = $service->create($vehicle, ['category' => 'documentation', 'amount' => 5000], $actor);
    expect($expense->status)->toBe('pending')
        ->and((float) $vehicle->fresh()->landed_cost)->toBe(400000.0);

    $service->approve($expense, $actor);
    expect((float) $vehicle->fresh()->landed_cost)->toBe(405000.0)
        ->and($expense->fresh()->added_to_landed_cost)->toBeTrue();

    // Approving again is blocked (no double counting).
    expect(fn () => $service->approve($expense->fresh(), $actor))->toThrow(RuntimeException::class);
    expect((float) $vehicle->fresh()->landed_cost)->toBe(405000.0);
});

it('reverses an approved expense back out of landed cost', function () {
    $actor = superAdmin();
    $vehicle = stockVehicle();
    $service = app(VehicleExpenseService::class);

    $expense = $service->create($vehicle, ['category' => 'refurbishment', 'amount' => 12000], $actor);
    $service->approve($expense, $actor);
    expect((float) $vehicle->fresh()->landed_cost)->toBe(412000.0);

    $reversal = $service->reverse($expense->fresh(), $actor, 'Wrong vehicle');

    expect((float) $reversal->amount)->toBe(-12000.0)
        ->and($reversal->reversal_of)->toBe($expense->id)
        ->and($expense->fresh()->status)->toBe('reversed')
        ->and((float) $vehicle->fresh()->landed_cost)->toBe(400000.0);
});

it('rejects a pending expense without touching landed cost', function () {
    $actor = superAdmin();
    $vehicle = stockVehicle();
    $service = app(VehicleExpenseService::class);

    $expense = $service->create($vehicle, ['category' => 'other', 'amount' => 3000], $actor);
    $service->reject($expense, $actor, 'Not applicable');

    expect($expense->fresh()->status)->toBe('rejected')
        ->and((float) $vehicle->fresh()->landed_cost)->toBe(400000.0);
});

it('lets a permitted user approve an expense through the web endpoint', function () {
    $user = userWithPermissions(['vehicles.view', 'vehicles.update', 'refurbishment.create', 'refurbishment.approve'], scope: 'all');
    $vehicle = stockVehicle();

    $this->actingAs($user)->post("/admin/inventory/{$vehicle->id}/expenses", [
        'category' => 'rto', 'amount' => 8000,
    ])->assertRedirect();

    $expense = $vehicle->expenses()->first();
    $this->actingAs($user)->post("/admin/vehicle-expenses/{$expense->id}/approve")->assertRedirect();

    expect((float) $vehicle->fresh()->landed_cost)->toBe(408000.0);
});

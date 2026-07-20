<?php

use App\Domain\Branches\Models\Branch;
use App\Domain\Inventory\Actions\TransferVehicleAction;
use App\Domain\Inventory\Actions\UpdateVehiclePriceAction;
use App\Domain\Inventory\Enums\VehicleStatus;
use App\Domain\Inventory\Models\Vehicle;

it('transfers a vehicle to another branch and records the movement', function () {
    $actor = superAdmin();
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $vehicle = Vehicle::query()->create([
        'stock_number' => 'STK-T-1', 'make' => 'Tata', 'model' => 'Nexon',
        'branch_id' => $branchA->id, 'status' => VehicleStatus::InStock->value,
        'purchase_price' => 500000, 'landed_cost' => 500000,
    ]);

    app(TransferVehicleAction::class)->execute($vehicle, $branchB, $actor, 'Bay 4');

    expect($vehicle->fresh()->branch_id)->toBe($branchB->id)
        ->and($vehicle->fresh()->parking_location)->toBe('Bay 4');

    $this->assertDatabaseHas('vehicle_movements', [
        'vehicle_id' => $vehicle->id, 'type' => 'branch_transfer',
        'from_branch_id' => $branchA->id, 'to_branch_id' => $branchB->id,
    ]);
});

it('blocks transferring to the same branch', function () {
    $actor = superAdmin();
    $branch = Branch::factory()->create();
    $vehicle = Vehicle::query()->create([
        'stock_number' => 'STK-T-2', 'make' => 'Kia', 'model' => 'Seltos',
        'branch_id' => $branch->id, 'status' => VehicleStatus::InStock->value,
    ]);

    app(TransferVehicleAction::class)->execute($vehicle, $branch, $actor);
})->throws(RuntimeException::class);

it('records price changes in history', function () {
    $actor = superAdmin();
    $vehicle = Vehicle::query()->create([
        'stock_number' => 'STK-P-1', 'make' => 'Honda', 'model' => 'City',
        'status' => VehicleStatus::ReadyForSale->value, 'asking_price' => 600000,
    ]);

    app(UpdateVehiclePriceAction::class)->execute($vehicle, 'asking', 575000, $actor, 'Festive offer');

    expect((float) $vehicle->fresh()->asking_price)->toBe(575000.0);
    $this->assertDatabaseHas('vehicle_prices', [
        'vehicle_id' => $vehicle->id, 'price_type' => 'asking', 'new_price' => 575000.00,
    ]);
});

it('scopes stock lists to the user branch', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $user = userWithPermissions(['vehicles.view'], scope: 'own_branch', attributes: ['branch_id' => $branchA->id]);

    Vehicle::query()->create(['stock_number' => 'STK-A', 'make' => 'A', 'model' => 'A', 'branch_id' => $branchA->id, 'status' => 'in_stock']);
    $other = Vehicle::query()->create(['stock_number' => 'STK-B', 'make' => 'B', 'model' => 'B', 'branch_id' => $branchB->id, 'status' => 'in_stock']);

    $this->actingAs($user)->get('/admin/inventory')->assertOk();
    $this->actingAs($user)->get("/admin/inventory/{$other->id}")->assertForbidden();
});

it('hides landed cost from users without the purchase-cost permission', function () {
    $user = userWithPermissions(['vehicles.view'], scope: 'all');
    $vehicle = Vehicle::query()->create([
        'stock_number' => 'STK-COST', 'make' => 'M', 'model' => 'S',
        'status' => 'in_stock', 'purchase_price' => 400000, 'landed_cost' => 450000,
    ]);

    $this->actingAs($user)
        ->get("/admin/inventory/{$vehicle->id}")
        ->assertInertia(fn ($page) => $page->missing('vehicle.landed_cost')->missing('vehicle.purchase_price'));
});

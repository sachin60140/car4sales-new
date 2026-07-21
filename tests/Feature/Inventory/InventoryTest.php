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

it('adds a stock vehicle from the admin form', function () {
    $branch = Branch::factory()->create();
    $user = userWithPermissions(['vehicles.view', 'vehicles.create', 'vehicles.view-purchase-cost'], scope: 'all');

    // Mirror the real form payload: blank money fields arrive as explicit nulls.
    $response = $this->actingAs($user)->post('/admin/inventory', [
        'make' => 'Hyundai', 'model' => 'Creta', 'variant' => 'SX',
        'registration_number' => 'MH12XY9999', 'manufacturing_year' => 2021,
        'fuel_type' => 'Petrol', 'transmission' => 'Manual', 'body_type' => 'SUV',
        'odometer_km' => 28000, 'ownership_serial' => 1, 'branch_id' => $branch->id,
        'status' => VehicleStatus::InStock->value,
        'purchase_price' => null, 'landed_cost' => 900000, 'asking_price' => 1050000,
    ]);

    $vehicle = Vehicle::query()->where('registration_number', 'MH12XY9999')->firstOrFail();
    $response->assertRedirect("/admin/inventory/{$vehicle->id}");

    expect($vehicle->stock_number)->not->toBeEmpty()
        ->and($vehicle->status)->toBe(VehicleStatus::InStock)
        ->and((float) $vehicle->purchase_price)->toBe(0.0)
        ->and((float) $vehicle->landed_cost)->toBe(900000.0)
        ->and($vehicle->created_by)->toBe($user->id)
        ->and($vehicle->slug)->not->toBeEmpty();

    // The initial status is recorded in history.
    $this->assertDatabaseHas('vehicle_status_histories', [
        'vehicle_id' => $vehicle->id, 'to_status' => VehicleStatus::InStock->value,
    ]);
});

it('forbids adding stock without the create permission', function () {
    $user = userWithPermissions(['vehicles.view'], scope: 'all');

    $this->actingAs($user)
        ->post('/admin/inventory', ['make' => 'Tata', 'model' => 'Punch', 'status' => VehicleStatus::InStock->value])
        ->assertForbidden();

    expect(Vehicle::query()->where('model', 'Punch')->exists())->toBeFalse();
});

it('rejects a duplicate registration number when adding stock', function () {
    $user = userWithPermissions(['vehicles.view', 'vehicles.create'], scope: 'all');
    Vehicle::query()->create([
        'stock_number' => 'STK-DUP', 'make' => 'Kia', 'model' => 'Sonet',
        'registration_number' => 'MH01AA1111', 'status' => 'in_stock',
    ]);

    $this->actingAs($user)
        ->post('/admin/inventory', [
            'make' => 'Kia', 'model' => 'Sonet', 'registration_number' => 'MH01AA1111',
            'status' => VehicleStatus::InStock->value,
        ])
        ->assertSessionHasErrors('registration_number');

    expect(Vehicle::query()->where('registration_number', 'MH01AA1111')->count())->toBe(1);
});

it('requires make and model when adding stock', function () {
    $user = userWithPermissions(['vehicles.view', 'vehicles.create'], scope: 'all');

    $this->actingAs($user)
        ->post('/admin/inventory', ['status' => VehicleStatus::InStock->value])
        ->assertSessionHasErrors(['make', 'model']);
});

it('records acquisition and purchase details when adding stock', function () {
    $user = userWithPermissions(['vehicles.view', 'vehicles.create'], scope: 'all');
    $purchaser = userWithPermissions(['vehicles.view'], scope: 'all');

    $this->actingAs($user)->post('/admin/inventory', [
        'make' => 'Tata', 'model' => 'Nexon', 'status' => VehicleStatus::InStock->value,
        'acquisition_source' => 'dealer', 'seller_name' => 'ABC Motors', 'seller_contact' => '9876500000',
        'purchased_by' => $purchaser->id, 'purchased_at' => '2026-07-10', 'purchase_reference' => 'INV-778',
    ])->assertRedirect();

    $vehicle = Vehicle::query()->where('model', 'Nexon')->firstOrFail();
    expect($vehicle->acquisition_source)->toBe('dealer')
        ->and($vehicle->seller_name)->toBe('ABC Motors')
        ->and($vehicle->purchased_by)->toBe($purchaser->id)
        ->and($vehicle->purchased_at->toDateString())->toBe('2026-07-10')
        ->and($vehicle->purchase_reference)->toBe('INV-778');
});

it('updates acquisition details without clearing other fields', function () {
    $user = userWithPermissions(['vehicles.view', 'vehicles.update'], scope: 'all');
    $vehicle = Vehicle::query()->create([
        'stock_number' => 'STK-ACQ', 'make' => 'Honda', 'model' => 'City',
        'registration_number' => 'MH02CC2222', 'status' => 'in_stock',
    ]);

    $this->actingAs($user)->patch("/admin/inventory/{$vehicle->id}", [
        'acquisition_source' => 'individual', 'seller_name' => 'Ramesh',
    ])->assertRedirect();

    $vehicle->refresh();
    expect($vehicle->acquisition_source)->toBe('individual')
        ->and($vehicle->seller_name)->toBe('Ramesh')
        // Untouched fields survive the partial update.
        ->and($vehicle->registration_number)->toBe('MH02CC2222');
});

it('rejects an unknown acquisition source', function () {
    $user = userWithPermissions(['vehicles.view', 'vehicles.create'], scope: 'all');

    $this->actingAs($user)
        ->post('/admin/inventory', [
            'make' => 'X', 'model' => 'Y', 'status' => VehicleStatus::InStock->value,
            'acquisition_source' => 'spaceship',
        ])
        ->assertSessionHasErrors('acquisition_source');
});

it('ignores purchase cost fields from users without the cost permission', function () {
    $user = userWithPermissions(['vehicles.view', 'vehicles.create'], scope: 'all');

    $this->actingAs($user)->post('/admin/inventory', [
        'make' => 'Ford', 'model' => 'EcoSport', 'status' => VehicleStatus::InStock->value,
        'landed_cost' => 700000, 'purchase_price' => 650000, 'asking_price' => 800000,
    ]);

    $vehicle = Vehicle::query()->where('model', 'EcoSport')->firstOrFail();
    expect((float) $vehicle->landed_cost)->toBe(0.0)
        ->and((float) $vehicle->purchase_price)->toBe(0.0)
        ->and((float) $vehicle->asking_price)->toBe(800000.0);
});

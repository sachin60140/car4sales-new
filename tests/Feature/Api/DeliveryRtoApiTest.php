<?php

use App\Domain\Deliveries\Models\Delivery;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\RTO\Enums\RtoStatus;
use App\Domain\RTO\Models\RtoCase;

function mobileToken(array $permissions): string
{
    $user = userWithPermissions([...$permissions, 'access-mobile']);

    return test()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Test Device',
    ])->json('data.token');
}

it('lists deliveries through the mobile api envelope', function () {
    $token = mobileToken(['deliveries.view']);

    $this->getJson('/api/v1/deliveries', ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data', 'meta' => ['pagination' => ['total', 'per_page', 'current_page', 'last_page']]]);
});

it('denies delivery listing without permission', function () {
    $token = mobileToken([]);

    $this->getJson('/api/v1/deliveries', ['Authorization' => "Bearer {$token}"])
        ->assertForbidden();
});

it('lists RTO cases and returns allowed transitions on show', function () {
    $token = mobileToken(['rto-cases.view']);

    $vehicle = Vehicle::query()->create([
        'stock_number' => 'STK-API'.fake()->unique()->numerify('###'),
        'make' => 'Kia', 'model' => 'Seltos', 'status' => 'delivered',
        'asking_price' => 900000, 'purchase_price' => 800000, 'landed_cost' => 820000,
    ]);
    $case = RtoCase::query()->create([
        'rto_number' => 'RTO-API-'.fake()->unique()->numerify('####'),
        'vehicle_id' => $vehicle->id,
        'status' => RtoStatus::CaseCreated->value,
    ]);

    $this->getJson('/api/v1/rto-cases', ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->getJson("/api/v1/rto-cases/{$case->id}", ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('data.rto_number', $case->rto_number)
        ->assertJsonStructure(['data' => ['allowed_transitions', 'total_expenses', 'movements', 'holds']]);
});

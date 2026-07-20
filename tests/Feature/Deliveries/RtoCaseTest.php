<?php

use App\Domain\Inventory\Models\Vehicle;
use App\Domain\RTO\Actions\RtoCaseAction;
use App\Domain\RTO\Enums\RtoStatus;
use App\Domain\RTO\Models\RtoCase;
use App\Support\Workflow\InvalidTransitionException;

function bareRtoCase(): RtoCase
{
    $vehicle = Vehicle::query()->create([
        'stock_number' => 'STK-'.fake()->unique()->numerify('R####'),
        'make' => 'Tata', 'model' => 'Nexon',
        'registration_number' => 'UP32 '.fake()->unique()->numerify('CD####'),
        'registration_state' => 'UP',
        'status' => 'delivered',
        'asking_price' => 700000, 'purchase_price' => 600000, 'landed_cost' => 620000,
    ]);

    return RtoCase::query()->create([
        'rto_number' => 'RTO-TEST-'.fake()->unique()->numerify('####'),
        'vehicle_id' => $vehicle->id,
        'status' => RtoStatus::CaseCreated->value,
        'from_rto' => 'UP',
    ]);
}

it('advances an RTO case along an allowed transition', function () {
    $admin = superAdmin();
    $case = bareRtoCase();

    app(RtoCaseAction::class)->transition($case, RtoStatus::SellerDocumentsPending, $admin, 'Docs requested');

    expect($case->fresh()->status)->toBe(RtoStatus::SellerDocumentsPending)
        ->and($case->fresh()->statusHistories()->count())->toBe(1);
});

it('rejects an invalid RTO transition', function () {
    $admin = superAdmin();
    $case = bareRtoCase();

    expect(fn () => app(RtoCaseAction::class)->transition($case, RtoStatus::Closed, $admin))
        ->toThrow(InvalidTransitionException::class);
});

it('tracks document custody, defaulting the source to the last holder', function () {
    $admin = superAdmin();
    $case = bareRtoCase();
    $action = app(RtoCaseAction::class);

    $action->recordMovement($case, 'Original RC', 'RTO Executive', $admin, 'Showroom');
    $second = $action->recordMovement($case, 'Original RC', 'RTO Office', $admin);

    expect($second->from_holder)->toBe('RTO Executive')
        ->and($case->movements()->count())->toBe(2);
});

it('records expenses and totals them', function () {
    $admin = superAdmin();
    $case = bareRtoCase();
    $action = app(RtoCaseAction::class);

    $action->addExpense($case, 'transfer_fee', 1500, $admin, 'REF-1');
    $action->addExpense($case, 'smart_card', 500, $admin);

    expect((float) $case->fresh()->totalExpenses())->toBe(2000.0);
});

it('places and releases payment holds, tracking the held amount', function () {
    $admin = superAdmin();
    $case = bareRtoCase();
    $action = app(RtoCaseAction::class);

    $hold = $action->addHold($case, 5000, 'RC pending', $admin);
    expect((float) $case->fresh()->hold_amount)->toBe(5000.0);

    $action->releaseHold($hold, $admin);
    expect($hold->fresh()->status)->toBe('released')
        ->and((float) $case->fresh()->hold_amount)->toBe(0.0);
});

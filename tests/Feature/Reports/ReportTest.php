<?php

use App\Domain\Bookings\Actions\ConfirmBookingAction;
use App\Domain\Bookings\Actions\CreateBookingAction;
use App\Domain\Branches\Models\Branch;
use App\Domain\Inventory\Enums\VehicleStatus;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\Reports\Support\ReportRegistry;
use App\Domain\SalesLeads\Actions\CreateSalesLeadAction;
use App\Models\User;

function reportVehicle(?int $branchId = null): Vehicle
{
    return Vehicle::query()->create([
        'stock_number' => 'STK-'.fake()->unique()->numerify('RPT###'),
        'make' => 'Honda', 'model' => 'City', 'branch_id' => $branchId,
        'status' => VehicleStatus::ReadyForSale->value,
        'asking_price' => 700000, 'minimum_selling_price' => 600000,
        'purchase_price' => 560000, 'landed_cost' => 580000,
    ]);
}

function reportBooking(User $actor, ?int $branchId = null): void
{
    $lead = app(CreateSalesLeadAction::class)->execute(
        ['name' => 'Buyer', 'mobile' => '900'.fake()->unique()->numerify('#######'), 'source' => 'walk_in', 'branch_id' => $branchId],
        $actor,
    );
    $booking = app(CreateBookingAction::class)->execute($lead, reportVehicle($branchId), [
        'selling_price' => 690000, 'discount_amount' => 0, 'booking_amount' => 20000, 'branch_id' => $branchId,
    ], $actor);
    app(ConfirmBookingAction::class)->execute($booking, $actor);
}

it('only lists reports the user is permitted to run', function () {
    $user = userWithPermissions(['reports.access-reports', 'bookings.view']);

    $reports = app(ReportRegistry::class)->forUser($user);

    expect($reports->has('sales-bookings'))->toBeTrue()
        ->and($reports->has('finance-disbursement'))->toBeFalse()
        ->and($reports->has('inventory-ageing'))->toBeFalse();
});

it('runs the sales bookings report with rows, summary and chart', function () {
    $admin = superAdmin();
    reportBooking($admin);
    reportBooking($admin);

    $result = app(ReportRegistry::class)->get('sales-bookings')->run([], $admin);

    expect($result->rows)->toHaveCount(2)
        ->and($result->summary)->not->toBeEmpty()
        ->and($result->chart)->toHaveKey('labels');
});

it('scopes report data to the viewer\'s branch', function () {
    $admin = superAdmin();
    $branchA = Branch::query()->create(['code' => 'RA', 'name' => 'A', 'slug' => 'ra', 'is_active' => true]);
    $branchB = Branch::query()->create(['code' => 'RB', 'name' => 'B', 'slug' => 'rb', 'is_active' => true]);

    reportBooking($admin, $branchA->id);
    reportBooking($admin, $branchB->id);

    $branchUser = userWithPermissions(['bookings.view'], 'own_branch', ['branch_id' => $branchA->id]);

    $result = app(ReportRegistry::class)->get('sales-bookings')->run([], $branchUser);

    expect($result->rows)->toHaveCount(1);
});

it('exports a report as CSV', function () {
    $admin = superAdmin();
    reportBooking($admin);

    $this->actingAs($admin)
        ->get('/admin/reports/sales-bookings/export?format=csv')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('exports a report as PDF', function () {
    $admin = superAdmin();

    $response = $this->actingAs($admin)->get('/admin/reports/sales-bookings/export?format=pdf');

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('denies report access without the reports permission', function () {
    $user = userWithPermissions(['bookings.view']);

    $this->actingAs($user)->get('/admin/reports')->assertForbidden();
});

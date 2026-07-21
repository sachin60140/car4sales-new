<?php

use App\Domain\Approvals\Services\ApprovalEngine;
use App\Domain\Bookings\Actions\ConfirmBookingAction;
use App\Domain\Bookings\Actions\CreateBookingAction;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Models\Booking;
use App\Domain\Inventory\Enums\VehicleStatus;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\SalesLeads\Actions\CreateSalesLeadAction;
use App\Domain\SalesLeads\Enums\SalesLeadStatus;
use App\Domain\SalesLeads\Models\SalesLead;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

function bookingVehicle(array $overrides = []): Vehicle
{
    return Vehicle::query()->create(array_merge([
        'stock_number' => 'STK-'.fake()->unique()->numerify('B####'),
        'make' => 'Honda', 'model' => 'City',
        'status' => VehicleStatus::ReadyForSale->value,
        'asking_price' => 800000, 'minimum_selling_price' => 720000,
        'purchase_price' => 650000, 'landed_cost' => 680000,
    ], $overrides));
}

function bookingLead(User $creator): SalesLead
{
    return app(CreateSalesLeadAction::class)->execute(
        ['name' => 'Buyer', 'mobile' => '90000'.fake()->unique()->numerify('#####'), 'source' => 'walk_in'],
        $creator,
    );
}

it('confirms a booking within discount authority, locking the vehicle', function () {
    $admin = superAdmin();
    $vehicle = bookingVehicle();
    $lead = bookingLead($admin);

    $booking = app(CreateBookingAction::class)->execute($lead, $vehicle, [
        'selling_price' => 790000, 'discount_amount' => 10000, 'booking_amount' => 20000,
    ], $admin);
    expect($booking->status)->toBe(BookingStatus::Draft);

    app(ConfirmBookingAction::class)->execute($booking, $admin);

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($vehicle->fresh()->status)->toBe(VehicleStatus::Booked)
        ->and($vehicle->fresh()->reserved_booking_id)->toBe($booking->id)
        ->and($lead->fresh()->status)->toBe(SalesLeadStatus::Booking);
});

it('prevents double-booking the same vehicle', function () {
    $admin = superAdmin();
    $vehicle = bookingVehicle();

    $first = app(CreateBookingAction::class)->execute(bookingLead($admin), $vehicle, ['selling_price' => 790000], $admin);
    app(ConfirmBookingAction::class)->execute($first, $admin);

    $second = app(CreateBookingAction::class)->execute(bookingLead($admin), $vehicle, ['selling_price' => 800000], $admin);

    expect(fn () => app(ConfirmBookingAction::class)->execute($second, $admin))
        ->toThrow(RuntimeException::class, 'already has an active booking');
});

it('routes an excess discount through approval and reserves the vehicle', function () {
    $this->seed(RolePermissionSeeder::class); // roles + discount approval limits
    $admin = superAdmin();
    $exec = User::factory()->create();
    $exec->assignRole('Sales Executive');

    $vehicle = bookingVehicle();
    $booking = app(CreateBookingAction::class)->execute(bookingLead($admin), $vehicle, [
        'selling_price' => 780000, 'discount_amount' => 20000,
    ], $exec);

    app(ConfirmBookingAction::class)->execute($booking, $exec);

    expect($booking->fresh()->status)->toBe(BookingStatus::ApprovalPending)
        ->and($booking->fresh()->approval_request_id)->not->toBeNull()
        ->and($vehicle->fresh()->status)->toBe(VehicleStatus::Reserved);

    // Approving the discount (single Sales Manager step) confirms the booking.
    app(ApprovalEngine::class)->approve($booking->fresh()->approvalRequest, $admin);

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($vehicle->fresh()->status)->toBe(VehicleStatus::Booked)
        ->and($booking->fresh()->discount_approved_by)->toBe($admin->id);
});

it('blocks booking a vehicle that is not sellable', function () {
    $admin = superAdmin();
    $vehicle = bookingVehicle(['status' => VehicleStatus::UnderRefurbishment->value]);
    $booking = app(CreateBookingAction::class)->execute(bookingLead($admin), $vehicle, ['selling_price' => 790000], $admin);

    expect(fn () => app(ConfirmBookingAction::class)->execute($booking, $admin))
        ->toThrow(RuntimeException::class, 'not available');
});

it('creates a booking from the web endpoint scoped to the sales executive', function () {
    $user = userWithPermissions(['bookings.view', 'bookings.create', 'sales-leads.view'], scope: 'all');
    $vehicle = bookingVehicle();
    $lead = bookingLead($user);

    $this->actingAs($user)->post('/admin/bookings', [
        'sales_lead_id' => $lead->id, 'vehicle_id' => $vehicle->id,
        'selling_price' => 790000, 'payment_mode' => 'cash',
    ])->assertRedirect();

    expect(Booking::query()->where('vehicle_id', $vehicle->id)->exists())->toBeTrue();
});

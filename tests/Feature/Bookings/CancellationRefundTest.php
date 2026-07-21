<?php

use App\Domain\Approvals\Services\ApprovalEngine;
use App\Domain\Bookings\Actions\CancelBookingAction;
use App\Domain\Bookings\Actions\ConfirmBookingAction;
use App\Domain\Bookings\Actions\CreateBookingAction;
use App\Domain\Bookings\Actions\RefundAction;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Inventory\Enums\VehicleStatus;
use Database\Seeders\RolePermissionSeeder;

function confirmedBooking(): array
{
    $admin = superAdmin();
    $vehicle = bookingVehicle();
    $lead = bookingLead($admin);
    $booking = app(CreateBookingAction::class)->execute($lead, $vehicle, [
        'selling_price' => 790000, 'discount_amount' => 5000, 'booking_amount' => 25000,
    ], $admin);
    app(ConfirmBookingAction::class)->execute($booking, $admin);

    return [$booking->fresh(), $vehicle->fresh(), $admin];
}

it('requires a reason and releases the vehicle only after cancellation approval', function () {
    [$booking, $vehicle, $admin] = confirmedBooking();

    $cancellation = app(CancelBookingAction::class)->request($booking, 'Customer changed mind', 20000, 5000, $admin);
    expect($booking->fresh()->status)->toBe(BookingStatus::CancellationRequested)
        // Vehicle still held until the cancellation is authorised.
        ->and($vehicle->fresh()->status)->toBe(VehicleStatus::Booked);

    app(CancelBookingAction::class)->approve($cancellation->fresh(), $admin);

    expect($booking->fresh()->status)->toBe(BookingStatus::RefundPending)
        ->and($vehicle->fresh()->status)->toBe(VehicleStatus::ReadyForSale)
        ->and($vehicle->fresh()->reserved_booking_id)->toBeNull();
});

it('forfeits when there is no refund amount', function () {
    [$booking, , $admin] = confirmedBooking();

    $cancellation = app(CancelBookingAction::class)->request($booking, 'No show', 0, 25000, $admin);
    app(CancelBookingAction::class)->approve($cancellation->fresh(), $admin);

    expect($booking->fresh()->status)->toBe(BookingStatus::Forfeited);
});

it('requires refund approval before paying, then closes the booking', function () {
    $this->seed(RolePermissionSeeder::class); // roles + refund approval limits
    [$booking, , $admin] = confirmedBooking();

    $cancellation = app(CancelBookingAction::class)->request($booking, 'Changed mind', 20000, 5000, $admin);
    app(CancelBookingAction::class)->approve($cancellation->fresh(), $admin);

    $refund = app(RefundAction::class)->initiate($cancellation->fresh(), $admin);
    expect($refund->status)->toBe('pending')
        ->and($refund->approval_request_id)->not->toBeNull();

    // Paying before approval is blocked.
    expect(fn () => app(RefundAction::class)->pay($refund->fresh(), $admin, 'bank_transfer'))
        ->toThrow(RuntimeException::class);

    app(ApprovalEngine::class)->approve($refund->fresh()->approvalRequest, $admin);
    expect($refund->fresh()->status)->toBe('approved');

    app(RefundAction::class)->pay($refund->fresh(), $admin, 'bank_transfer', 'TXN99');

    expect($refund->fresh()->status)->toBe('paid')
        ->and($booking->fresh()->status)->toBe(BookingStatus::Refunded)
        ->and((float) $booking->payments()->where('type', 'refund')->sum('amount'))->toBe(-20000.0);
});

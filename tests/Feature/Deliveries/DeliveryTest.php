<?php

use App\Domain\Bookings\Actions\ConfirmBookingAction;
use App\Domain\Bookings\Actions\CreateBookingAction;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Models\Booking;
use App\Domain\Deliveries\Actions\DeliveryAction;
use App\Domain\Deliveries\Enums\DeliveryStatus;
use App\Domain\Deliveries\Models\Delivery;
use App\Domain\Inventory\Enums\VehicleStatus;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\Payments\Services\LedgerService;
use App\Domain\Payments\Services\PaymentService;
use App\Domain\RTO\Enums\RtoStatus;
use App\Domain\RTO\Models\RtoCase;
use App\Domain\SalesLeads\Actions\CreateSalesLeadAction;
use App\Domain\SalesLeads\Enums\SalesLeadStatus;
use App\Domain\SalesLeads\Models\SalesLead;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

function deliveryVehicle(array $overrides = []): Vehicle
{
    return Vehicle::query()->create(array_merge([
        'stock_number' => 'STK-'.fake()->unique()->numerify('D####'),
        'make' => 'Hyundai', 'model' => 'Creta',
        'registration_number' => 'UP32 '.fake()->unique()->numerify('AB####'),
        'registration_state' => 'UP',
        'status' => VehicleStatus::ReadyForSale->value,
        'asking_price' => 800000, 'minimum_selling_price' => 700000,
        'purchase_price' => 650000, 'landed_cost' => 680000,
    ], $overrides));
}

function deliveryLead(User $creator): SalesLead
{
    return app(CreateSalesLeadAction::class)->execute(
        ['name' => 'Delivery Buyer', 'mobile' => '90001'.fake()->unique()->numerify('#####'), 'source' => 'walk_in'],
        $creator,
    );
}

/** A confirmed, fully-settled, KYC-verified cash booking ready for delivery. */
function settledBooking(User $admin): Booking
{
    $vehicle = deliveryVehicle();
    $lead = deliveryLead($admin);

    $booking = app(CreateBookingAction::class)->execute($lead, $vehicle, [
        'selling_price' => 790000, 'discount_amount' => 0, 'booking_amount' => 20000, 'payment_mode' => 'cash',
    ], $admin);
    app(ConfirmBookingAction::class)->execute($booking, $admin);

    $booking->refresh();
    $booking->customer->update(['kyc_status' => 'verified']);

    $ledger = app(LedgerService::class)->forBooking($booking);
    app(PaymentService::class)->record($booking, [
        'type' => 'balance', 'amount' => $ledger->outstanding(), 'method' => 'bank_transfer',
    ], $admin);

    return $booking->fresh();
}

it('opens a delivery and auto-derives the system checklist items', function () {
    $admin = superAdmin();
    $booking = settledBooking($admin);

    $delivery = app(DeliveryAction::class)->create($booking, $admin);

    expect($delivery->status)->toBe(DeliveryStatus::ApprovalPending)
        ->and($delivery->delivery_number)->toStartWith('DLV-')
        ->and($delivery->chk_booking_confirmed)->toBeTrue()
        ->and($delivery->chk_kyc_verified)->toBeTrue()
        ->and($delivery->chk_payment_complete)->toBeTrue()
        ->and($delivery->chk_finance_disbursed)->toBeTrue() // n/a for cash
        ->and($delivery->approvalChecklistComplete())->toBeFalse(); // manual checks still pending
});

it('is idempotent — one active delivery per booking', function () {
    $admin = superAdmin();
    $booking = settledBooking($admin);
    $action = app(DeliveryAction::class);

    $first = $action->create($booking, $admin);
    $second = $action->create($booking, $admin);

    expect($second->id)->toBe($first->id);
});

it('enforces one active delivery per booking at the database level', function () {
    $admin = superAdmin();
    $booking = settledBooking($admin);
    $first = app(DeliveryAction::class)->create($booking, $admin);

    // A raw second active delivery for the same booking must violate the unique index.
    expect(fn () => Delivery::query()->create([
        'delivery_number' => 'DLV-DUP-1',
        'booking_id' => $booking->id,
        'vehicle_id' => $booking->vehicle_id,
        'customer_id' => $booking->customer_id,
        'status' => DeliveryStatus::ApprovalPending->value,
    ]))->toThrow(UniqueConstraintViolationException::class);

    // Cancelling the first frees the slot for a fresh delivery.
    $first->update(['status' => DeliveryStatus::Cancelled->value]);
    $second = Delivery::query()->create([
        'delivery_number' => 'DLV-DUP-2',
        'booking_id' => $booking->id,
        'vehicle_id' => $booking->vehicle_id,
        'customer_id' => $booking->customer_id,
        'status' => DeliveryStatus::ApprovalPending->value,
    ]);
    expect($second->exists)->toBeTrue();
});

it('blocks approval until every checklist item is complete', function () {
    $admin = superAdmin();
    $booking = settledBooking($admin);
    $action = app(DeliveryAction::class);

    $delivery = $action->create($booking, $admin);

    expect(fn () => $action->approve($delivery, $admin))
        ->toThrow(RuntimeException::class, 'checklist');
});

it('approves and hands over, delivering the vehicle and spawning an RTO case', function () {
    $admin = superAdmin();
    $booking = settledBooking($admin);
    $action = app(DeliveryAction::class);

    $delivery = $action->create($booking, $admin);
    $action->setManualChecks($delivery, [
        'chk_quality_check' => true, 'chk_insurance' => true, 'chk_rto_papers_signed' => true,
        'chk_accessories' => true, 'chk_cleaned' => true, 'chk_documents_prepared' => true,
    ]);
    $delivery = $action->refreshChecklist($delivery);

    expect($delivery->approvalChecklistComplete())->toBeTrue();

    $action->approve($delivery, $admin);

    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Approved)
        ->and($booking->vehicle->fresh()->status)->toBe(VehicleStatus::DeliveryPending)
        ->and($booking->fresh()->status)->toBe(BookingStatus::ReadyForDelivery);

    $action->complete($delivery->fresh(), $admin, [
        'odometer' => 45000, 'fuel_level' => 'Half', 'dc_keys' => true, 'dc_rc_copy' => true,
    ]);

    $delivery->refresh();
    expect($delivery->status)->toBe(DeliveryStatus::Delivered)
        ->and($delivery->delivered_at)->not->toBeNull()
        ->and($booking->vehicle->fresh()->status)->toBe(VehicleStatus::Delivered)
        ->and($booking->fresh()->status)->toBe(BookingStatus::Delivered)
        ->and($booking->lead->fresh()->status)->toBe(SalesLeadStatus::Delivered);

    $case = RtoCase::query()->where('delivery_id', $delivery->id)->first();
    expect($case)->not->toBeNull()
        ->and($case->status)->toBe(RtoStatus::CaseCreated)
        ->and($case->rto_number)->toStartWith('RTO-')
        ->and($case->buyer_customer_id)->toBe($booking->customer_id)
        ->and($case->from_rto)->toBe('UP');
});

it('cannot hand over a delivery that has not been approved', function () {
    $admin = superAdmin();
    $booking = settledBooking($admin);
    $action = app(DeliveryAction::class);

    $delivery = $action->create($booking, $admin);

    expect(fn () => $action->complete($delivery, $admin))
        ->toThrow(RuntimeException::class, 'approved');
});

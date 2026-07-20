<?php

use App\Domain\Bookings\Actions\ConfirmBookingAction;
use App\Domain\Bookings\Actions\CreateBookingAction;
use App\Domain\Inventory\Enums\VehicleStatus;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\Payments\Enums\LedgerHead;
use App\Domain\Payments\Services\LedgerService;
use App\Domain\Payments\Services\PaymentService;
use App\Domain\SalesLeads\Actions\CreateSalesLeadAction;
use App\Models\User;

function ledgerBooking(array $bookingData = []): array
{
    $admin = superAdmin();
    $vehicle = Vehicle::query()->create([
        'stock_number' => 'STK-'.fake()->unique()->numerify('L####'), 'make' => 'Honda', 'model' => 'City',
        'status' => VehicleStatus::ReadyForSale->value, 'asking_price' => 800000, 'minimum_selling_price' => 700000,
        'purchase_price' => 650000, 'landed_cost' => 680000, 'branch_id' => null,
    ]);
    $lead = app(CreateSalesLeadAction::class)->execute(['name' => 'L', 'mobile' => '90000'.fake()->unique()->numerify('#####'), 'source' => 'walk_in'], $admin);
    $booking = app(CreateBookingAction::class)->execute($lead, $vehicle, array_merge([
        'selling_price' => 790000, 'discount_amount' => 10000, 'exchange_adjustment' => 50000,
    ], $bookingData), $admin);
    app(ConfirmBookingAction::class)->execute($booking, $admin);

    return [$booking->fresh(), $admin];
}

it('opens the ledger at confirmation with the correct outstanding', function () {
    [$booking] = ledgerBooking();
    $ledger = app(LedgerService::class)->forBooking($booking);

    // 790000 selling − 10000 discount − 50000 exchange = 730000 owed.
    expect($ledger)->not->toBeNull()
        ->and($ledger->outstanding())->toBe(730000.0);
});

it('is idempotent when opening an already-open ledger', function () {
    [$booking, $admin] = ledgerBooking();

    $first = app(LedgerService::class)->forBooking($booking);
    $again = app(LedgerService::class)->openForBooking($booking, $admin);

    expect($again->id)->toBe($first->id)
        ->and($again->entries()->count())->toBe($first->entries()->count());
});

it('posts a payment as a credit that reduces outstanding', function () {
    [$booking, $admin] = ledgerBooking();

    app(PaymentService::class)->record($booking, ['amount' => 100000, 'method' => 'upi'], $admin);

    $ledger = app(LedgerService::class)->forBooking($booking);
    expect($ledger->outstanding())->toBe(630000.0)
        ->and($booking->payments()->where('status', 'received')->count())->toBe(1);
    $this->assertDatabaseHas('receipts', ['booking_id' => $booking->id, 'amount' => 100000.00]);
});

it('reverses a payment and restores the outstanding via a mirror entry', function () {
    [$booking, $admin] = ledgerBooking();
    $payment = app(PaymentService::class)->record($booking, ['amount' => 100000, 'method' => 'upi'], $admin);

    app(PaymentService::class)->reverse($payment->fresh(), $admin, 'Wrong amount');

    $ledger = app(LedgerService::class)->forBooking($booking);
    expect($ledger->outstanding())->toBe(730000.0)
        ->and($payment->fresh()->status)->toBe('reversed');
    // The original credit + a reversing debit both exist (append-only).
    expect($ledger->entries()->where('head', 'payment')->count())->toBe(2);
});

it('never allows a reversal to be reversed', function () {
    [$booking, $admin] = ledgerBooking();
    $ledger = app(LedgerService::class)->forBooking($booking);
    $entry = $ledger->entries()->where('head', 'selling_price')->first();

    $reversal = app(LedgerService::class)->reverse($entry, $admin, 'test');

    expect(fn () => app(LedgerService::class)->reverse($reversal->fresh(), $admin, 'again'))
        ->toThrow(RuntimeException::class);
    // And the original cannot be reversed twice.
    expect(fn () => app(LedgerService::class)->reverse($entry->fresh(), $admin, 'again'))
        ->toThrow(RuntimeException::class, 'already been reversed');
});

it('records a payment through the web endpoint and posts to the ledger', function () {
    $user = userWithPermissions(['bookings.view', 'bookings.update'], scope: 'all');
    [$booking] = ledgerBooking();

    $this->actingAs($user)->post("/admin/bookings/{$booking->id}/payment", [
        'type' => 'booking', 'amount' => 50000, 'method' => 'cash',
    ])->assertRedirect();

    expect(app(LedgerService::class)->forBooking($booking)->outstanding())->toBe(680000.0);
});

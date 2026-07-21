<?php

namespace App\Domain\Bookings\Actions;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Models\Booking;
use App\Domain\Bookings\Models\BookingCancellation;
use App\Domain\Inventory\Enums\VehicleStatus;
use App\Domain\Inventory\Models\Vehicle;
use App\Models\User;
use App\Support\Workflow\WorkflowService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Booking cancellation. A cancellation must carry a reason and be authorised;
 * only an authorised cancellation releases the vehicle back to stock (spec §22).
 */
class CancelBookingAction
{
    public function __construct(private readonly WorkflowService $workflow) {}

    public function request(Booking $booking, string $reason, float $refundAmount, float $forfeitAmount, User $actor): BookingCancellation
    {
        if ($booking->status->isTerminal()) {
            throw new RuntimeException('A completed booking cannot be cancelled.');
        }

        return DB::transaction(function () use ($booking, $reason, $refundAmount, $forfeitAmount, $actor) {
            $cancellation = BookingCancellation::query()->create([
                'booking_id' => $booking->id,
                'reason' => $reason,
                'refund_amount' => $refundAmount,
                'forfeit_amount' => $forfeitAmount,
                'requested_by' => $actor->id,
                'status' => 'requested',
            ]);

            if ($booking->status !== BookingStatus::CancellationRequested) {
                $this->workflow->transition($booking, BookingStatus::CancellationRequested, $actor, 'Cancellation requested: '.$reason);
            }

            return $cancellation;
        });
    }

    public function approve(BookingCancellation $cancellation, User $approver): BookingCancellation
    {
        return DB::transaction(function () use ($cancellation, $approver) {
            $booking = $cancellation->booking;

            $cancellation->update(['status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now()]);

            // A vehicle can hold several bookings — only release it back to stock
            // when no *other* active booking is still holding it.
            $vehicle = Vehicle::query()->whereKey($booking->vehicle_id)->lockForUpdate()->first();
            if ($vehicle !== null) {
                $otherHold = Booking::query()
                    ->where('vehicle_id', $vehicle->id)
                    ->where('id', '!=', $booking->id)
                    ->whereIn('status', $this->heldStatuses())
                    ->first();

                if ($otherHold === null) {
                    $vehicle->update(['reserved_booking_id' => null]);
                    $target = $vehicle->published_web ? VehicleStatus::Published : VehicleStatus::ReadyForSale;
                    if (in_array($vehicle->status, [VehicleStatus::Reserved, VehicleStatus::Booked, VehicleStatus::DeliveryPending], true)) {
                        $this->workflow->transition($vehicle, $target, $approver, 'Booking cancelled', force: true);
                    }
                } elseif ($vehicle->reserved_booking_id === $booking->id) {
                    // Hand the reservation to a remaining active booking.
                    $vehicle->update(['reserved_booking_id' => $otherHold->id]);
                }
            }

            $this->workflow->transition($booking, BookingStatus::Cancelled, $approver, 'Cancellation approved', force: true);

            // Decide the next money state.
            if ((float) $cancellation->refund_amount > 0) {
                $this->workflow->transition($booking, BookingStatus::RefundPending, $approver, 'Refund pending', force: true);
            } elseif ((float) $cancellation->forfeit_amount > 0) {
                $this->workflow->transition($booking, BookingStatus::Forfeited, $approver, 'Amount forfeited', force: true);
            }

            return $cancellation;
        });
    }

    /** @return array<int, string> */
    private function heldStatuses(): array
    {
        return array_values(array_filter(
            array_map(fn (BookingStatus $s) => $s->holdsVehicle() ? $s->value : null, BookingStatus::cases()),
        ));
    }
}

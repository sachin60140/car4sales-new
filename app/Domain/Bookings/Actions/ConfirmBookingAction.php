<?php

namespace App\Domain\Bookings\Actions;

use App\Domain\Approvals\Services\ApprovalEngine;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Models\Booking;
use App\Domain\Bookings\Support\DiscountAuthority;
use App\Domain\Inventory\Enums\VehicleStatus;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\Payments\Services\LedgerService;
use App\Domain\SalesLeads\Enums\SalesLeadStatus;
use App\Models\User;
use App\Support\Workflow\WorkflowService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Confirms a booking under a row lock on the vehicle. Guarantees a single active
 * booking per vehicle (double-booking block), reserves/locks the vehicle, and
 * routes excess discounts through the approval engine before confirming.
 */
class ConfirmBookingAction
{
    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly DiscountAuthority $authority,
        private readonly ApprovalEngine $approvals,
    ) {}

    public function execute(Booking $booking, User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $actor) {
            /** @var Vehicle $vehicle */
            $vehicle = Vehicle::query()->whereKey($booking->vehicle_id)->lockForUpdate()->firstOrFail();

            // Double-booking guard: no other active booking may hold this vehicle.
            $conflict = Booking::query()
                ->where('vehicle_id', $vehicle->id)
                ->where('id', '!=', $booking->id)
                ->whereIn('status', $this->heldStatuses())
                ->exists();

            if ($conflict || ($vehicle->reserved_booking_id !== null && $vehicle->reserved_booking_id !== $booking->id)) {
                throw new RuntimeException('This vehicle already has an active booking.');
            }

            if (! in_array($vehicle->status, [VehicleStatus::ReadyForSale, VehicleStatus::Published, VehicleStatus::Reserved], true)) {
                throw new RuntimeException('The vehicle is not available for booking.');
            }

            $needsApproval = $this->authority->requiresApproval(
                $actor,
                (float) $booking->discount_amount,
                (float) $booking->selling_price,
                $vehicle->minimum_selling_price !== null ? (float) $vehicle->minimum_selling_price : null,
            );

            // Reserve the vehicle so it is held while we confirm / await approval.
            $vehicle->update(['reserved_booking_id' => $booking->id]);
            if ($vehicle->status !== VehicleStatus::Reserved && $vehicle->status !== VehicleStatus::Booked) {
                $this->workflow->transition($vehicle, VehicleStatus::Reserved, $actor, 'Reserved for booking '.$booking->booking_number, force: true);
            }

            if ($needsApproval) {
                $reasons = [];
                if ($vehicle->minimum_selling_price !== null && (float) $booking->selling_price < (float) $vehicle->minimum_selling_price) {
                    $reasons[] = 'below_minimum_price';
                }

                $request = $this->approvals->open(
                    subject: $booking,
                    module: 'discounts',
                    requestedAmount: (float) $booking->discount_amount,
                    requester: $actor,
                    roleChain: DiscountAuthority::CHAIN,
                    reasons: $reasons,
                    reason: 'Discount of ₹'.number_format((float) $booking->discount_amount).' on '.$booking->booking_number,
                    branchId: $booking->branch_id,
                );

                $booking->update(['approval_request_id' => $request->id]);
                $this->workflow->transition($booking, BookingStatus::ApprovalPending, $actor, 'Awaiting discount approval', force: true);

                return $booking->fresh();
            }

            return $this->finalizeConfirm($booking, $vehicle, $actor);
        });
    }

    /**
     * Called by the approval engine (Booking::onApprovalDecided) once the discount
     * approval is decided.
     */
    public function onDiscountDecision(Booking $booking, string $decision, User $approver): void
    {
        DB::transaction(function () use ($booking, $decision, $approver) {
            $vehicle = Vehicle::query()->whereKey($booking->vehicle_id)->lockForUpdate()->firstOrFail();

            if ($decision === 'approved') {
                $booking->update(['discount_approved_by' => $approver->id]);
                $this->finalizeConfirm($booking, $vehicle, $approver);
            } else {
                // Rejected: release the vehicle and send the booking back to draft.
                $this->releaseVehicle($vehicle, $approver);
                $this->workflow->transition($booking, BookingStatus::Draft, $approver, 'Discount rejected', force: true);
            }
        });
    }

    private function finalizeConfirm(Booking $booking, Vehicle $vehicle, User $actor): Booking
    {
        $this->workflow->transition($vehicle, VehicleStatus::Booked, $actor, 'Booked ('.$booking->booking_number.')', force: true);
        $this->workflow->transition($booking, BookingStatus::Confirmed, $actor, 'Booking confirmed', force: true);

        // Open the customer ledger with the deal heads (selling price / discount).
        app(LedgerService::class)->openForBooking($booking->fresh(), $actor);

        // Advance the sales lead.
        if ($booking->lead !== null && ! in_array($booking->lead->status, [SalesLeadStatus::Delivered], true)) {
            $this->workflow->transition($booking->lead, SalesLeadStatus::Booking, $actor, 'Booking '.$booking->booking_number, force: true);
        }

        return $booking->fresh();
    }

    private function releaseVehicle(Vehicle $vehicle, User $actor): void
    {
        $vehicle->update(['reserved_booking_id' => null]);
        $target = $vehicle->published_web ? VehicleStatus::Published : VehicleStatus::ReadyForSale;
        if ($vehicle->status !== $target) {
            $this->workflow->transition($vehicle, $target, $actor, 'Released from booking', force: true);
        }
    }

    /** @return array<int, string> */
    private function heldStatuses(): array
    {
        return array_values(array_filter(
            array_map(fn (BookingStatus $s) => $s->holdsVehicle() ? $s->value : null, BookingStatus::cases()),
        ));
    }
}

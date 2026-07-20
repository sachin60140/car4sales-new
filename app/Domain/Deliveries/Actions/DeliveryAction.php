<?php

namespace App\Domain\Deliveries\Actions;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Models\Booking;
use App\Domain\Deliveries\Enums\DeliveryStatus;
use App\Domain\Deliveries\Models\Delivery;
use App\Domain\Finance\Enums\FinanceStatus;
use App\Domain\Finance\Models\FinanceApplication;
use App\Domain\Inventory\Enums\VehicleStatus;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\Payments\Services\LedgerService;
use App\Domain\RTO\Actions\CreateRtoCaseAction;
use App\Domain\RTO\Models\RtoCase;
use App\Domain\SalesLeads\Enums\SalesLeadStatus;
use App\Models\User;
use App\Support\Workflow\WorkflowService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Drives the delivery lifecycle (spec §24):
 *   create → (auto-derived approval checklist) → approve → complete/handover.
 *
 * Completion transitions the vehicle, booking and sales lead to Delivered and
 * spawns the RTO transfer case automatically.
 */
class DeliveryAction
{
    /** Checks that are computed from other modules and cannot be toggled by hand. */
    private const AUTO_CHECKS = [
        'chk_booking_confirmed', 'chk_kyc_verified', 'chk_payment_complete', 'chk_finance_disbursed',
    ];

    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly NumberSequenceService $sequences,
        private readonly LedgerService $ledger,
        private readonly CreateRtoCaseAction $createRtoCase,
    ) {}

    /**
     * Open a delivery for a booking. One active (non-cancelled) delivery per booking.
     */
    public function create(Booking $booking, User $actor): Delivery
    {
        if (in_array($booking->status, [BookingStatus::Draft, BookingStatus::ApprovalPending, BookingStatus::Cancelled, BookingStatus::Refunded, BookingStatus::Forfeited], true)) {
            throw new RuntimeException('The booking must be confirmed before a delivery can be opened.');
        }

        $existing = $this->activeDelivery($booking);
        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($booking, $actor) {
                $delivery = new Delivery([
                    'booking_id' => $booking->id,
                    'vehicle_id' => $booking->vehicle_id,
                    'customer_id' => $booking->customer_id,
                    'branch_id' => $booking->branch_id,
                    'status' => DeliveryStatus::ApprovalPending->value,
                    'created_by' => $actor->id,
                ]);
                $delivery->delivery_number = $this->sequences->next('delivery', $booking->branch_id);
                $delivery->save();

                $this->applyAutoChecks($delivery->fresh(), $booking);

                return $delivery->fresh();
            });
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent request won the race; return the delivery it created.
            $winner = $this->activeDelivery($booking);
            if ($winner !== null) {
                return $winner;
            }
            throw $e;
        }
    }

    /** The current live (non-cancelled) delivery for a booking, if any. */
    private function activeDelivery(Booking $booking): ?Delivery
    {
        return Delivery::query()
            ->where('booking_id', $booking->id)
            ->where('status', '!=', DeliveryStatus::Cancelled->value)
            ->first();
    }

    /**
     * Re-derive the automatic checklist items (booking, KYC, payment, finance).
     */
    public function refreshChecklist(Delivery $delivery): Delivery
    {
        $this->applyAutoChecks($delivery, $delivery->booking);

        return $delivery->fresh();
    }

    /**
     * Set the manual checklist items (quality, insurance, RTO papers, etc.).
     *
     * @param  array<string, bool>  $checks
     */
    public function setManualChecks(Delivery $delivery, array $checks): Delivery
    {
        $manual = array_diff(Delivery::APPROVAL_CHECKS, self::AUTO_CHECKS);
        $update = [];
        foreach ($manual as $field) {
            if (array_key_exists($field, $checks)) {
                $update[$field] = (bool) $checks[$field];
            }
        }

        if ($update !== []) {
            $delivery->update($update);
        }

        return $delivery->fresh();
    }

    /**
     * Approve a delivery once every checklist item is satisfied. Moves the vehicle
     * to delivery-pending and the booking to ready-for-delivery.
     */
    public function approve(Delivery $delivery, User $actor): Delivery
    {
        // Bring the auto checks up to date first so approval reflects live state.
        $this->applyAutoChecks($delivery, $delivery->booking);
        $delivery->refresh();

        if ($delivery->status !== DeliveryStatus::ApprovalPending) {
            throw new RuntimeException('Only a pending delivery can be approved.');
        }

        if (! $delivery->approvalChecklistComplete()) {
            throw new RuntimeException('All approval checklist items must be complete before approval.');
        }

        return DB::transaction(function () use ($delivery, $actor) {
            $delivery->update([
                'status' => DeliveryStatus::Approved->value,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            /** @var Vehicle $vehicle */
            $vehicle = Vehicle::query()->whereKey($delivery->vehicle_id)->lockForUpdate()->firstOrFail();
            if ($vehicle->status === VehicleStatus::Booked) {
                $this->workflow->transition($vehicle, VehicleStatus::DeliveryPending, $actor, 'Delivery '.$delivery->delivery_number.' approved', force: true);
            }

            $booking = $delivery->booking;
            if ($booking !== null && ! in_array($booking->status, [BookingStatus::ReadyForDelivery, BookingStatus::Delivered], true)) {
                $this->workflow->transition($booking, BookingStatus::ReadyForDelivery, $actor, 'Delivery approved', force: true);
            }

            app(\App\Domain\Notifications\Services\NotificationDispatcher::class)->deliveryApproved($delivery->fresh());

            return $delivery->fresh();
        });
    }

    /**
     * Record the handover and complete the delivery. Delivers the vehicle/booking/
     * lead and auto-creates the RTO transfer case.
     *
     * @param  array<string, mixed>  $handover
     */
    public function complete(Delivery $delivery, User $actor, array $handover = []): Delivery
    {
        if ($delivery->status !== DeliveryStatus::Approved) {
            throw new RuntimeException('The delivery must be approved before handover.');
        }

        return DB::transaction(function () use ($delivery, $actor, $handover) {
            $handoverFields = [
                'odometer', 'fuel_level', 'dc_keys', 'dc_spare_key', 'dc_rc_copy', 'dc_insurance',
                'dc_invoice', 'dc_tool_kit', 'dc_spare_wheel', 'dc_accessories',
                'customer_photo_path', 'delivery_photo_path', 'customer_signature_path',
                'employee_signature_path', 'remarks',
            ];
            $update = ['status' => DeliveryStatus::Delivered->value, 'delivered_at' => now(), 'delivered_by' => $actor->id];
            foreach ($handoverFields as $field) {
                if (array_key_exists($field, $handover)) {
                    $update[$field] = $handover[$field];
                }
            }
            $delivery->update($update);

            /** @var Vehicle $vehicle */
            $vehicle = Vehicle::query()->whereKey($delivery->vehicle_id)->lockForUpdate()->firstOrFail();
            if (in_array($vehicle->status, [VehicleStatus::Booked, VehicleStatus::DeliveryPending], true)) {
                $this->workflow->transition($vehicle, VehicleStatus::Delivered, $actor, 'Delivered ('.$delivery->delivery_number.')', force: true);
            }

            $booking = $delivery->booking;
            if ($booking !== null && $booking->status !== BookingStatus::Delivered) {
                $this->workflow->transition($booking, BookingStatus::Delivered, $actor, 'Vehicle delivered', force: true);

                if ($booking->lead !== null && $booking->lead->status !== SalesLeadStatus::Delivered) {
                    $this->workflow->transition($booking->lead, SalesLeadStatus::Delivered, $actor, 'Vehicle delivered', force: true);
                }
            }

            // Spawn the RTO ownership-transfer case.
            $this->createRtoCase->fromDelivery($delivery->fresh(), $actor);

            app(\App\Domain\Notifications\Services\NotificationDispatcher::class)->deliveryCompleted($delivery->fresh());

            return $delivery->fresh();
        });
    }

    /**
     * Recompute and persist the auto-derived approval checks from live module state.
     */
    private function applyAutoChecks(Delivery $delivery, ?Booking $booking): void
    {
        if ($booking === null) {
            return;
        }

        $bookingConfirmed = ! in_array($booking->status, [
            BookingStatus::Draft, BookingStatus::ApprovalPending,
            BookingStatus::Cancelled, BookingStatus::Refunded, BookingStatus::Forfeited,
        ], true);

        $kycVerified = $booking->customer !== null && $booking->customer->kyc_status === 'verified';

        $ledger = $this->ledger->forBooking($booking);
        $paymentComplete = $ledger !== null && $ledger->outstanding() <= 0.01;

        if ($booking->payment_mode === 'finance') {
            $financeDisbursed = FinanceApplication::query()
                ->where('booking_id', $booking->id)
                ->where('status', FinanceStatus::Disbursed->value)
                ->exists();
        } else {
            $financeDisbursed = true; // Not applicable for non-finance deals.
        }

        $delivery->update([
            'chk_booking_confirmed' => $bookingConfirmed,
            'chk_kyc_verified' => $kycVerified,
            'chk_payment_complete' => $paymentComplete,
            'chk_finance_disbursed' => $financeDisbursed,
        ]);
    }
}

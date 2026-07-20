<?php

namespace App\Domain\Bookings\Actions;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\Approvals\Services\ApprovalEngine;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Models\Booking;
use App\Domain\Bookings\Models\BookingCancellation;
use App\Domain\Bookings\Models\BookingPayment;
use App\Domain\Bookings\Models\Refund;
use App\Models\User;
use App\Support\Workflow\WorkflowService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Refund processing. Refunds require approval before they can be paid (spec §22).
 */
class RefundAction
{
    public const CHAIN = ['Accounts Manager', 'Branch Manager', 'Director'];

    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly ApprovalEngine $approvals,
        private readonly WorkflowService $workflow,
    ) {}

    /**
     * Raise a refund for an approved cancellation and open its approval.
     */
    public function initiate(BookingCancellation $cancellation, User $actor): Refund
    {
        if ($cancellation->status !== 'approved') {
            throw new RuntimeException('Refunds can only be raised for an approved cancellation.');
        }

        return DB::transaction(function () use ($cancellation, $actor) {
            $refund = Refund::query()->create([
                'refund_number' => $this->sequences->next('refund'),
                'booking_id' => $cancellation->booking_id,
                'booking_cancellation_id' => $cancellation->id,
                'amount' => $cancellation->refund_amount,
                'status' => 'pending',
                'created_by' => $actor->id,
            ]);

            $request = $this->approvals->open(
                subject: $refund,
                module: 'refunds',
                requestedAmount: (float) $refund->amount,
                requester: $actor,
                roleChain: self::CHAIN,
                reason: 'Refund of ₹'.number_format((float) $refund->amount).' for '.$cancellation->booking->booking_number,
                branchId: $cancellation->booking->branch_id,
            );

            $refund->update(['approval_request_id' => $request->id]);

            return $refund->fresh();
        });
    }

    /**
     * Pay an approved refund: records the outgoing payment and closes the booking.
     */
    public function pay(Refund $refund, User $actor, string $method, ?string $reference = null): Refund
    {
        if ($refund->status !== 'approved') {
            throw new RuntimeException('Only an approved refund can be paid.');
        }

        return DB::transaction(function () use ($refund, $actor, $method, $reference) {
            $refund->update([
                'status' => 'paid',
                'method' => $method,
                'reference' => $reference,
                'paid_at' => now(),
            ]);

            $payment = BookingPayment::query()->create([
                'payment_number' => $this->sequences->next('booking_payment'),
                'booking_id' => $refund->booking_id,
                'type' => 'refund',
                'amount' => -1 * (float) $refund->amount,
                'method' => $method,
                'reference' => $reference,
                'status' => 'received',
                'received_by' => $actor->id,
                'remarks' => 'Refund '.$refund->refund_number,
            ]);

            // Post the refund as a ledger debit (money returned to the customer).
            $ledger = app(\App\Domain\Payments\Services\LedgerService::class)->forBooking($refund->booking);
            if ($ledger !== null) {
                app(\App\Domain\Payments\Services\LedgerService::class)->post(
                    $ledger, \App\Domain\Payments\Enums\LedgerHead::Refund, 'debit',
                    (float) $refund->amount, $actor, $payment, 'Refund '.$refund->refund_number,
                );
            }

            $this->workflow->transition($refund->booking, BookingStatus::Refunded, $actor, 'Refund paid', force: true);

            return $refund;
        });
    }
}

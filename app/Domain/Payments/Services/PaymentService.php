<?php

namespace App\Domain\Payments\Services;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\Bookings\Models\Booking;
use App\Domain\Bookings\Models\BookingPayment;
use App\Domain\Payments\Enums\LedgerHead;
use App\Domain\Payments\Models\Receipt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Records customer payments against a booking. Every payment posts a credit to
 * the customer ledger and generates a receipt. Payments are reversed (never
 * deleted) — a reversal posts a mirror ledger entry and marks the payment.
 */
class PaymentService
{
    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly LedgerService $ledger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(Booking $booking, array $data, User $actor): BookingPayment
    {
        $amount = (float) $data['amount'];
        if ($amount <= 0) {
            throw new RuntimeException('Payment amount must be positive.');
        }

        return DB::transaction(function () use ($booking, $data, $actor, $amount) {
            // Ensure the ledger exists before recording the payment so opening
            // does not re-post this payment.
            $ledger = $this->ledger->openForBooking($booking, $actor);

            $payment = BookingPayment::query()->create([
                'payment_number' => $this->sequences->next('booking_payment'),
                'booking_id' => $booking->id,
                'type' => $data['type'] ?? 'booking',
                'amount' => $amount,
                'method' => $data['method'] ?? 'cash',
                'account_id' => $data['account_id'] ?? null,
                'reference' => $data['reference'] ?? null,
                'proof_path' => $data['proof_path'] ?? null,
                'status' => 'received',
                'received_by' => $actor->id,
                'remarks' => $data['remarks'] ?? null,
            ]);

            $this->ledger->post($ledger, LedgerHead::Payment, 'credit', $amount, $actor, $payment, 'Payment '.$payment->payment_number);

            Receipt::query()->create([
                'receipt_number' => $this->sequences->next('receipt'),
                'booking_id' => $booking->id,
                'booking_payment_id' => $payment->id,
                'amount' => $amount,
                'created_by' => $actor->id,
            ]);

            return $payment;
        });
    }

    /**
     * Reverse a received payment: mirrors the ledger entry and marks the payment.
     */
    public function reverse(BookingPayment $payment, User $actor, string $remarks): BookingPayment
    {
        if ($payment->status !== 'received') {
            throw new RuntimeException('Only a received payment can be reversed.');
        }

        return DB::transaction(function () use ($payment, $actor, $remarks) {
            $payment->update(['status' => 'reversed', 'remarks' => $remarks]);

            $ledger = $this->ledger->forBooking($payment->booking);
            if ($ledger !== null) {
                $entry = $ledger->entries()
                    ->where('reference_type', $payment->getMorphClass())
                    ->where('reference_id', $payment->id)
                    ->whereNull('reversal_of')
                    ->first();

                if ($entry !== null) {
                    $this->ledger->reverse($entry, $actor, $remarks);
                }
            }

            return $payment->fresh();
        });
    }
}

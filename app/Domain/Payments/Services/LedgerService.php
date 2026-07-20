<?php

namespace App\Domain\Payments\Services;

use App\Domain\Bookings\Models\Booking;
use App\Domain\Payments\Enums\LedgerHead;
use App\Domain\Payments\Models\CustomerLedger;
use App\Domain\Payments\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The customer ledger is the accounting source of truth for a booking. It is
 * append-only: posted entries are never edited or deleted — corrections are made
 * by posting a reversal entry that references the original (spec §23).
 *
 * Convention: debits are amounts the customer owes (selling price), credits are
 * amounts settled (discount, exchange, payments, finance disbursement). The
 * outstanding balance is debits − credits.
 */
class LedgerService
{
    /**
     * Open the ledger for a confirmed booking and post the opening deal heads.
     * Idempotent — returns the existing ledger if already opened.
     */
    public function openForBooking(Booking $booking, ?User $actor = null): CustomerLedger
    {
        $existing = CustomerLedger::query()->where('booking_id', $booking->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($booking, $actor) {
            $ledger = CustomerLedger::query()->create([
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'branch_id' => $booking->branch_id,
                'opened_at' => now(),
            ]);

            // Debit the selling price; credit the concessions.
            $this->post($ledger, LedgerHead::SellingPrice, 'debit', (float) $booking->selling_price, $actor, $booking, 'Booking confirmed');

            if ((float) $booking->discount_amount > 0) {
                $this->post($ledger, LedgerHead::Discount, 'credit', (float) $booking->discount_amount, $actor, $booking, 'Discount');
            }
            if ((float) $booking->exchange_adjustment > 0) {
                $this->post($ledger, LedgerHead::Exchange, 'credit', (float) $booking->exchange_adjustment, $actor, $booking, 'Exchange adjustment');
            }

            // Bring any payments already taken against the booking into the ledger.
            foreach ($booking->payments()->where('status', 'received')->get() as $payment) {
                $this->post($ledger, LedgerHead::Payment, $payment->amount < 0 ? 'debit' : 'credit', abs((float) $payment->amount), $actor, $payment, 'Payment '.$payment->payment_number);
            }

            return $ledger->fresh();
        });
    }

    /**
     * Post a ledger entry. Low-level append — always additive.
     */
    public function post(CustomerLedger $ledger, LedgerHead $head, string $type, float $amount, ?User $actor, ?Model $reference = null, ?string $remarks = null): LedgerEntry
    {
        if (! in_array($type, ['debit', 'credit'], true)) {
            throw new RuntimeException('Ledger entry type must be debit or credit.');
        }

        return $ledger->entries()->create([
            'type' => $type,
            'head' => $head->value,
            'amount' => round($amount, 2),
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'posted_by' => $actor?->id,
            'posted_at' => now(),
            'remarks' => $remarks,
        ]);
    }

    /**
     * Reverse a posted entry by appending a mirror entry of the opposite type.
     */
    public function reverse(LedgerEntry $entry, User $actor, string $remarks): LedgerEntry
    {
        if ($entry->isReversal()) {
            throw new RuntimeException('A reversal entry cannot itself be reversed.');
        }

        // Guard against double-reversal.
        if (LedgerEntry::query()->where('reversal_of', $entry->id)->exists()) {
            throw new RuntimeException('This entry has already been reversed.');
        }

        return DB::transaction(function () use ($entry, $actor, $remarks) {
            return $entry->ledger->entries()->create([
                'type' => $entry->type === 'debit' ? 'credit' : 'debit',
                'head' => $entry->head->value,
                'amount' => $entry->amount,
                'reference_type' => $entry->reference_type,
                'reference_id' => $entry->reference_id,
                'reversal_of' => $entry->id,
                'posted_by' => $actor->id,
                'posted_at' => now(),
                'remarks' => 'Reversal: '.$remarks,
            ]);
        });
    }

    public function forBooking(Booking $booking): ?CustomerLedger
    {
        return CustomerLedger::query()->where('booking_id', $booking->id)->first();
    }
}

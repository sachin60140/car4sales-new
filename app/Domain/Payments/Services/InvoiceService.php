<?php

namespace App\Domain\Payments\Services;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\Bookings\Models\Booking;
use App\Domain\Documents\Services\DocumentGenerator;
use App\Domain\Payments\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Generates the sale invoice for a booking (one per booking) and renders the PDF.
 */
class InvoiceService
{
    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly DocumentGenerator $documents,
    ) {}

    public function generate(Booking $booking, User $actor): Invoice
    {
        $existing = Invoice::query()->where('booking_id', $booking->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($booking, $actor) {
            $subtotal = (float) $booking->selling_price;
            $discount = (float) $booking->discount_amount;
            $total = $subtotal - $discount - (float) $booking->exchange_adjustment;

            $invoice = Invoice::query()->create([
                'invoice_number' => $this->sequences->next('invoice'),
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'other_charges' => 0,
                'total' => $total,
                'issued_at' => now(),
                'created_by' => $actor->id,
            ]);

            $booking->loadMissing(['customer', 'vehicle', 'branch']);

            $document = $this->documents->generate(
                templateKey: 'sale_invoice',
                view: 'documents.invoice',
                data: ['booking' => $booking, 'invoice' => $invoice],
                subject: $invoice,
                generatedBy: $actor,
                referencePrefix: 'INV',
            );

            $invoice->update(['generated_document_id' => $document->id]);

            return $invoice->fresh();
        });
    }
}

<?php

namespace App\Domain\Bookings\Actions;

use App\Domain\Bookings\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Number;

/**
 * Builds the customer booking slip / receipt PDF for a vehicle booking, with the
 * deal figures and standard used-vehicle booking terms & conditions under Indian
 * law (Motor Vehicles Act 1988, Sale of Goods Act 1930, etc.).
 */
class GenerateBookingSlipAction
{
    public function pdf(Booking $booking): \Barryvdh\DomPDF\PDF
    {
        $booking->loadMissing(['customer', 'vehicle', 'salesExecutive', 'branch', 'payments']);

        $netPayable = $booking->netPayable();
        $paid = $booking->paidAmount();

        return Pdf::loadView('documents.booking_slip', [
            'booking' => $booking,
            'customer' => $booking->customer,
            'vehicle' => $booking->vehicle,
            'company' => config('car4sales.public'),
            'generatedAt' => now(),
            'netPayable' => $netPayable,
            'paidAmount' => $paid,
            'balance' => max($netPayable - $paid, 0),
            'amountInWords' => $this->words($paid),
        ])->setPaper('a4');
    }

    private function words(float $amount): string
    {
        try {
            return ucwords(Number::spell((int) round($amount)).' rupees only');
        } catch (\Throwable) {
            return '';
        }
    }
}

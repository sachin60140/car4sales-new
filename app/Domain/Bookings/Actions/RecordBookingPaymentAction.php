<?php

namespace App\Domain\Bookings\Actions;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\Bookings\Models\Booking;
use App\Domain\Bookings\Models\BookingPayment;
use App\Models\User;

class RecordBookingPaymentAction
{
    public function __construct(private readonly NumberSequenceService $sequences) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Booking $booking, array $data, User $actor): BookingPayment
    {
        return BookingPayment::query()->create([
            'payment_number' => $this->sequences->next('booking_payment'),
            'booking_id' => $booking->id,
            'type' => $data['type'] ?? 'booking',
            'amount' => $data['amount'],
            'method' => $data['method'] ?? 'cash',
            'reference' => $data['reference'] ?? null,
            'proof_path' => $data['proof_path'] ?? null,
            'status' => 'received',
            'received_by' => $actor->id,
            'remarks' => $data['remarks'] ?? null,
        ]);
    }
}

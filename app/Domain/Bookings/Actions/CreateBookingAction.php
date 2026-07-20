<?php

namespace App\Domain\Bookings\Actions;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Models\Booking;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\SalesLeads\Models\SalesLead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Draft booking from a sales lead + vehicle. Confirmation (with the
 * row lock, double-booking guard and discount approval) happens separately in
 * ConfirmBookingAction.
 */
class CreateBookingAction
{
    public function __construct(private readonly NumberSequenceService $sequences) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(SalesLead $lead, Vehicle $vehicle, array $data, User $actor): Booking
    {
        return DB::transaction(function () use ($lead, $vehicle, $data, $actor) {
            $sellingPrice = (float) $data['selling_price'];
            $discount = (float) ($data['discount_amount'] ?? 0);

            return Booking::query()->create([
                'booking_number' => $this->sequences->next('booking'),
                'sales_lead_id' => $lead->id,
                'customer_id' => $lead->customer_id,
                'vehicle_id' => $vehicle->id,
                'selling_price' => $sellingPrice,
                'booking_amount' => $data['booking_amount'] ?? 0,
                'discount_amount' => $discount,
                'payment_mode' => $data['payment_mode'] ?? 'cash',
                'exchange_adjustment' => $data['exchange_adjustment'] ?? 0,
                'delivery_promised_at' => $data['delivery_promised_at'] ?? null,
                'telecaller_id' => $lead->telecaller_id,
                'sales_executive_id' => $data['sales_executive_id'] ?? $lead->sales_executive_id ?? $actor->id,
                'branch_id' => $vehicle->branch_id ?? $lead->branch_id,
                'accessories_promised' => $data['accessories_promised'] ?? null,
                'terms' => $data['terms'] ?? null,
                'status' => BookingStatus::Draft->value,
                'created_by' => $actor->id,
            ]);
        });
    }
}

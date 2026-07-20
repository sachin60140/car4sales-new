<?php

namespace App\Domain\RTO\Actions;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\Deliveries\Models\Delivery;
use App\Domain\RTO\Enums\RtoStatus;
use App\Domain\RTO\Models\RtoCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates the RTO ownership-transfer case that follows a completed delivery
 * (spec §25). Idempotent — one open case per delivery.
 */
class CreateRtoCaseAction
{
    public function __construct(
        private readonly NumberSequenceService $sequences,
    ) {}

    public function fromDelivery(Delivery $delivery, User $actor): RtoCase
    {
        $existing = RtoCase::query()->where('delivery_id', $delivery->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($delivery, $actor) {
            $vehicle = $delivery->vehicle;
            $booking = $delivery->booking;
            $sellerId = $vehicle?->purchase?->seller_id;

            $case = new RtoCase([
                'vehicle_id' => $delivery->vehicle_id,
                'booking_id' => $delivery->booking_id,
                'delivery_id' => $delivery->id,
                'seller_id' => $sellerId,
                'buyer_customer_id' => $delivery->customer_id,
                'from_rto' => $vehicle?->registration_state,
                'to_rto' => null,
                'sale_date' => $booking?->created_at?->toDateString(),
                'delivery_date' => optional($delivery->delivered_at)->toDateString() ?? now()->toDateString(),
                'branch_id' => $delivery->branch_id,
                'status' => RtoStatus::CaseCreated->value,
                'created_by' => $actor->id,
            ]);
            $case->rto_number = $this->sequences->next('rto_case', $delivery->branch_id);
            $case->save();

            $case->writeStatusHistory(null, RtoStatus::CaseCreated->value, $actor, 'Case opened from delivery '.$delivery->delivery_number);

            return $case->fresh();
        });
    }
}

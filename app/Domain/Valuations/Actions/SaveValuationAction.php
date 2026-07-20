<?php

namespace App\Domain\Valuations\Actions;

use App\Domain\PurchaseLeads\Models\PurchaseLead;
use App\Domain\Valuations\Models\VehicleValuation;
use App\Domain\Valuations\Services\ValuationCalculator;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a lead's valuation, recomputing the derived profit metrics
 * from the cost inputs via ValuationCalculator.
 */
class SaveValuationAction
{
    public function __construct(private readonly ValuationCalculator $calculator) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function execute(PurchaseLead $lead, array $input, User $actor): VehicleValuation
    {
        return DB::transaction(function () use ($lead, $input, $actor) {
            $derived = $this->calculator->calculate($input);

            $attributes = [
                ...$input,
                'recommended_price' => $derived['recommended_price'],
                'expected_gross_profit' => $derived['expected_gross_profit'],
                'expected_net_profit' => $derived['expected_net_profit'],
                'expected_margin_pct' => $derived['expected_margin_pct'],
                'vehicle_inspection_id' => $lead->latestInspection?->id,
                'prepared_by' => $actor->id,
                'status' => $input['status'] ?? 'submitted',
            ];

            return VehicleValuation::query()->updateOrCreate(
                ['purchase_lead_id' => $lead->id],
                $attributes,
            );
        });
    }
}

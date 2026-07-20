<?php

namespace App\Domain\Valuations\Models;

use App\Domain\Inspections\Models\VehicleInspection;
use App\Domain\PurchaseLeads\Models\PurchaseLead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VehicleValuation extends Model
{
    use LogsActivity;

    protected $fillable = [
        'purchase_lead_id', 'vehicle_inspection_id',
        'market_price', 'expected_retail_price', 'seller_expected_price', 'repair_estimate',
        'rto_expense', 'documentation_expense', 'transportation_expense', 'insurance_expense',
        'brokerage', 'holding_cost', 'other_costs', 'target_profit',
        'recommended_price', 'final_negotiated_price', 'expected_gross_profit',
        'expected_net_profit', 'expected_margin_pct', 'prepared_by', 'approved_by',
        'status', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'market_price' => 'decimal:2',
            'expected_retail_price' => 'decimal:2',
            'seller_expected_price' => 'decimal:2',
            'repair_estimate' => 'decimal:2',
            'rto_expense' => 'decimal:2',
            'documentation_expense' => 'decimal:2',
            'transportation_expense' => 'decimal:2',
            'insurance_expense' => 'decimal:2',
            'brokerage' => 'decimal:2',
            'holding_cost' => 'decimal:2',
            'other_costs' => 'decimal:2',
            'target_profit' => 'decimal:2',
            'recommended_price' => 'decimal:2',
            'final_negotiated_price' => 'decimal:2',
            'expected_gross_profit' => 'decimal:2',
            'expected_net_profit' => 'decimal:2',
            'expected_margin_pct' => 'decimal:2',
        ];
    }

    public function purchaseLead(): BelongsTo
    {
        return $this->belongsTo(PurchaseLead::class);
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(VehicleInspection::class, 'vehicle_inspection_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['recommended_price', 'final_negotiated_price', 'expected_net_profit', 'status'])
            ->logOnlyDirty()
            ->useLogName('valuation');
    }
}
